<?php

namespace App\Http\Controllers;

use App\Exports\MaintenanceReportExport;
use App\Models\MaintenanceRecord;
use App\Models\Procedure;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Services\Reports\ReportContextService;
use App\Services\Reports\TireReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    public function __construct(
        private readonly ReportContextService $reportContext
    ) {
    }

    public function index(Request $request)
    {
        $context = $this->reportContext->resolve();

        if (! $context) {
            return $this->missingActiveContextRedirect();
        }

        $last30Days = Carbon::now()->subDays(30);
        $last6Months = Carbon::now()->subMonths(6);

        $vehiclesQuery = $this->reportContext->vehicleQuery($context);
        $maintenanceBaseQuery = $this->reportContext->maintenanceQuery($context);

        $vehicles = (clone $vehiclesQuery)->count();

        $maintenance30Query = (clone $maintenanceBaseQuery)
            ->where('performed_at', '>=', $last30Days);

        $maintenanceCount30 = (clone $maintenance30Query)->count();
        $internalMaintenances30 = (clone $maintenance30Query)
            ->where('maintenance_type', 'internal')
            ->count();
        $externalMaintenances30 = (clone $maintenance30Query)
            ->where('maintenance_type', 'external')
            ->count();
        $maintenanceCost30 = (clone $maintenance30Query)->sum('total_cost');

        $maintenance6MonthsCount = (clone $maintenanceBaseQuery)
            ->where('performed_at', '>=', $last6Months)
            ->count();
        $maintenance6MonthsCost = (clone $maintenanceBaseQuery)
            ->where('performed_at', '>=', $last6Months)
            ->sum('total_cost');

        $maintenanceMonthlyAverage = $maintenance6MonthsCount / 6;
        $costMonthlyAverage = $maintenance6MonthsCost / 6;

        $maintenanceVariation = $maintenanceMonthlyAverage > 0
            ? (($maintenanceCount30 - $maintenanceMonthlyAverage) / $maintenanceMonthlyAverage) * 100
            : 0;

        $costVariation = $costMonthlyAverage > 0
            ? (($maintenanceCost30 - $costMonthlyAverage) / $costMonthlyAverage) * 100
            : 0;

        $averageMaintenanceCost = $maintenanceCount30 > 0
            ? $maintenanceCost30 / $maintenanceCount30
            : 0;

        $criticalVehicle = (clone $vehiclesQuery)
            ->withSum([
                'validMaintenances as maintenances_sum_total_cost',
            ], 'total_cost')
            ->orderByDesc('maintenances_sum_total_cost')
            ->first();

        $stockItems = StockItem::query()
            ->where('tenant_id', $context['tenant_id'])
            ->where('location_id', $context['location']->id)
            ->count();

        $filters = $this->maintenanceFilters($request, $context);
        $maintenancePreview = $this->buildMaintenanceReportData($request, $context, false);

        $reportVehicles = (clone $vehiclesQuery)
            ->orderBy('name')
            ->get(['id', 'name', 'plate']);

        $procedures = Procedure::query()
            ->where('tenant_id', $context['tenant_id'])
            ->where('location_id', $context['location']->id)
            ->orderBy('name')
            ->get(['id', 'name']);

        $providers = $this->reportContext->maintenanceQuery($context, true)
            ->whereNotNull('provider_name')
            ->where('provider_name', '<>', '')
            ->distinct()
            ->orderBy('provider_name')
            ->pluck('provider_name');

        return view('reports.index', compact(
            'vehicles',
            'maintenanceCount30',
            'internalMaintenances30',
            'externalMaintenances30',
            'maintenanceCost30',
            'maintenanceVariation',
            'costVariation',
            'averageMaintenanceCost',
            'criticalVehicle',
            'stockItems',
            'context',
            'filters',
            'maintenancePreview',
            'reportVehicles',
            'procedures',
            'providers'
        ));
    }

    public function exportMaintenance(Request $request)
    {
        $context = $this->reportContext->resolve();

        if (! $context) {
            return $this->missingActiveContextRedirect();
        }

        $data = $this->buildMaintenanceReportData($request, $context);

        $pdfData = $data;
        $pdfData['maintenances'] = $data['operational_maintenances_raw'];
        $pdfData['cancelledMaintenancesRaw'] = $data['maintenances_raw']
            ->filter(fn (MaintenanceRecord $maintenance) => $maintenance->cancelled_at !== null)
            ->values();

        $pdf = Pdf::loadView('reports.pdf.maintenance', $pdfData);

        return $pdf->download('relatorio-manutencoes.pdf');
    }

    public function exportMaintenanceExcel(Request $request)
    {
        $context = $this->reportContext->resolve();

        if (! $context) {
            return $this->missingActiveContextRedirect();
        }

        $data = $this->buildMaintenanceReportData($request, $context);

        return Excel::download(
            new MaintenanceReportExport($data),
            'relatorio-manutencoes.xlsx'
        );
    }

    public function tires(Request $request, TireReportService $tireReport)
    {
        $context = $this->reportContext->resolve();

        if (! $context) {
            return $this->missingActiveContextRedirect();
        }

        return view('reports.tires', $tireReport->build($context, $request));
    }

    private function buildMaintenanceReportData(Request $request, array $context, bool $full = true): array
    {
        $filters = $this->maintenanceFilters($request, $context);

        $maintenanceQuery = $this->applyMaintenanceFilters(
            $this->reportContext->maintenanceQuery($context, $filters['query_includes_cancelled'])
                ->with(['vehicle', 'procedure', 'canceller']),
            $filters
        )
            ->orderBy('performed_at', 'desc');

        if (! $full) {
            $maintenanceQuery->limit(10);
        }

        $displayMaintenances = $maintenanceQuery->get();

        $operationalMaintenances = $displayMaintenances
            ->filter(fn (MaintenanceRecord $maintenance) => $maintenance->cancelled_at === null)
            ->values();

        $maintenanceIds = $operationalMaintenances->pluck('id');

        $stockConsumed = $this->stockConsumedQuery($context, $maintenanceIds)
            ->with(['stockItem', 'maintenanceRecord.vehicle'])
            ->get();

        $cancelledMaintenances = $displayMaintenances
            ->filter(fn (MaintenanceRecord $maintenance) => $maintenance->cancelled_at !== null)
            ->values();

        $internalCount = $operationalMaintenances->where('maintenance_type', 'internal')->count();
        $externalCount = $operationalMaintenances->where('maintenance_type', 'external')->count();
        $totalCost = $operationalMaintenances->sum('total_cost');
        $averageCost = $operationalMaintenances->count() > 0 ? $totalCost / $operationalMaintenances->count() : 0;

        $procedureStats = $operationalMaintenances
            ->groupBy(fn ($maintenance) => $maintenance->procedure->name ?? 'Sem procedimento')
            ->map(function ($items, $procedure) {
                $total = $items->sum('total_cost');
                $count = $items->count();

                return [
                    'procedure' => $procedure,
                    'count' => $count,
                    'total' => $total,
                    'average' => $count > 0 ? $total / $count : 0,
                ];
            })
            ->sortByDesc('count')
            ->values();

        $vehicleCosts = $operationalMaintenances
            ->groupBy('vehicle_id')
            ->map(function ($items, $vehicleId) use ($filters, $context, $stockConsumed) {
                $vehicle = $items->first()->vehicle;
                $maintenanceIdsForVehicle = $items
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->all();

                $stockTotal = $stockConsumed
                    ->filter(fn ($movement) => in_array((int) $movement->maintenance_record_id, $maintenanceIdsForVehicle, true))
                    ->sum(fn ($movement) => (float) $movement->quantity * (float) $movement->unit_cost);

                return [
                    'vehicle' => $vehicle->name ?? '-',
                    'plate' => $vehicle->plate ?? '-',
                    'km_driven' => $this->sumVehicleLogDelta((int) $vehicleId, $context, $filters, 'km'),
                    'hours_driven' => $this->sumVehicleLogDelta((int) $vehicleId, $context, $filters, 'hours'),
                    'maintenance_total' => $items->sum('total_cost'),
                    'stock_total' => $stockTotal,
                    'total' => $items->sum('total_cost'),
                ];
            })
            ->sortByDesc('total')
            ->values();

        return [
            'filters' => $filters,
            'startDate' => $filters['start_date'],
            'endDate' => $filters['end_date'],
            'division' => $context['division'],
            'location' => $context['location'],
            'canViewCancelled' => $context['can_view_cancelled'],
            'maintenances_raw' => $displayMaintenances,
            'operational_maintenances_raw' => $operationalMaintenances,
            'maintenances' => $displayMaintenances
                ->map(fn (MaintenanceRecord $maintenance) => [
                    'created_at' => $maintenance->performed_at ?? $maintenance->created_at,
                    'performed_at' => $maintenance->performed_at,
                    'vehicle_name' => $maintenance->vehicle->name ?? '-',
                    'vehicle_plate' => $maintenance->vehicle->plate ?? '-',
                    'procedure_name' => $maintenance->procedure->name ?? '-',
                    'provider_name' => $maintenance->provider_name,
                    'maintenance_type' => $maintenance->maintenance_type,
                    'total_cost' => $maintenance->total_cost,
                    'cancelled_at' => $maintenance->cancelled_at,
                    'cancel_reason' => $context['can_view_cancelled'] ? $maintenance->cancel_reason : null,
                    'cancelled_by' => $context['can_view_cancelled'] ? $maintenance->canceller?->name : null,
                    'considered_in_totals' => $maintenance->cancelled_at === null,
                ])
                ->toArray(),
            'internalCount' => $internalCount,
            'externalCount' => $externalCount,
            'maintenanceCount' => $operationalMaintenances->count(),
            'displayMaintenanceCount' => $displayMaintenances->count(),
            'cancelledCount' => $cancelledMaintenances->count(),
            'totalCost' => $totalCost,
            'averageCost' => $averageCost,
            'procedureStats' => $procedureStats->toArray(),
            'vehicleCosts' => $vehicleCosts->toArray(),
            'stockConsumed' => $stockConsumed
                ->map(fn (StockMovement $movement) => [
                    'date' => $movement->created_at,
                    'maintenance_id' => $movement->maintenance_record_id,
                    'vehicle' => $movement->maintenanceRecord?->vehicle?->name ?? '-',
                    'plate' => $movement->maintenanceRecord?->vehicle?->plate ?? '-',
                    'item' => $movement->stockItem?->name ?? '-',
                    'quantity' => $movement->quantity,
                    'unit_cost' => $movement->unit_cost,
                    'total' => (float) $movement->quantity * (float) $movement->unit_cost,
                ])
                ->toArray(),
            'cancelledMaintenances' => $context['can_view_cancelled']
                ? $cancelledMaintenances->map(fn (MaintenanceRecord $maintenance) => [
                    'date' => $maintenance->cancelled_at,
                    'vehicle' => $maintenance->vehicle->name ?? '-',
                    'plate' => $maintenance->vehicle->plate ?? '-',
                    'procedure' => $maintenance->procedure->name ?? '-',
                    'total_cost' => $maintenance->total_cost,
                    'reason' => $maintenance->cancel_reason,
                    'cancelled_by' => $maintenance->canceller?->name,
                ])->toArray()
                : [],
        ];
    }

    private function maintenanceFilters(Request $request, array $context): array
    {
        $startDate = $request->filled('start_date')
            ? Carbon::parse($request->input('start_date'))->startOfDay()
            : Carbon::now()->subDays(30)->startOfDay();

        $endDate = $request->filled('end_date')
            ? Carbon::parse($request->input('end_date'))->endOfDay()
            : Carbon::now()->endOfDay();

        $status = $request->input('status', 'active');

        if (! in_array($status, ['active', 'cancelled', 'all'], true)) {
            $status = 'active';
        }

        $includeCancelled = $context['can_view_cancelled']
            && ($request->boolean('include_cancelled') || in_array($status, ['cancelled', 'all'], true));

        return [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'vehicle_id' => $request->integer('vehicle_id') ?: null,
            'maintenance_type' => in_array($request->input('maintenance_type'), ['internal', 'external'], true)
                ? $request->input('maintenance_type')
                : null,
            'procedure_id' => $request->integer('procedure_id') ?: null,
            'provider_name' => trim((string) $request->input('provider_name')) ?: null,
            'status' => $status,
            'include_cancelled' => $includeCancelled,
            'query_includes_cancelled' => $includeCancelled,
        ];
    }

    private function applyMaintenanceFilters(Builder $query, array $filters): Builder
    {
        $query->whereBetween('performed_at', [$filters['start_date'], $filters['end_date']]);

        if ($filters['vehicle_id']) {
            $query->where('vehicle_id', $filters['vehicle_id']);
        }

        if ($filters['maintenance_type']) {
            $query->where('maintenance_type', $filters['maintenance_type']);
        }

        if ($filters['procedure_id']) {
            $query->where('procedure_id', $filters['procedure_id']);
        }

        if ($filters['provider_name']) {
            $query->where('provider_name', 'like', '%' . $filters['provider_name'] . '%');
        }

        if ($filters['status'] === 'cancelled') {
            $query->whereNotNull('cancelled_at');
        } elseif ($filters['status'] === 'active') {
            $query->whereNull('cancelled_at');
        }

        return $query;
    }

    private function stockConsumedQuery(array $context, $maintenanceIds): Builder
    {
        return $this->reportContext->stockMovementQuery($context)
            ->whereIn('maintenance_record_id', $maintenanceIds)
            ->where('movement_type', 'out')
            ->whereNull('cancelled_at')
            ->whereNull('reversed_from_movement_id');
    }

    private function sumVehicleLogDelta(int $vehicleId, array $context, array $filters, string $type): float
    {
        return $this->reportContext->vehicleUpdateLogsQuery($context, $vehicleId)
            ->where('type', $type)
            ->whereBetween('created_at', [$filters['start_date'], $filters['end_date']])
            ->get()
            ->sum(fn ($log) => max(0, (float) $log->new_value - (float) $log->old_value));
    }

    private function missingActiveContextRedirect()
    {
        return redirect()
            ->route('portal')
            ->with('warning', 'Selecione uma divisão e uma unidade para continuar.');
    }
}
