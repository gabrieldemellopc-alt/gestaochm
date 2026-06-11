<?php

namespace App\Http\Controllers;

use App\Models\Vehicle;
use App\Models\MaintenanceRecord;
use App\Models\StockItem;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use App\Models\Division;
use App\Models\VehicleUpdateLog;
use App\Exports\MaintenanceReportExport;
use App\Services\ActiveContextService;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
public function index()
    {
        $divisionId =
            session('active_division_id');

        $context = $this->activeContext();

        if (! $context) {
            return $this->missingActiveContextRedirect();
        }

        $divisionId = $context['division']->id;
    
        $last30Days =
            Carbon::now()->subDays(30);
    
        $last6Months =
            Carbon::now()->subMonths(6);
    
        /*
        |--------------------------------------------------------------------------
        | BASE QUERIES
        |--------------------------------------------------------------------------
        */
    
        $vehiclesQuery =
            Vehicle::query()
                ->where('tenant_id', $context['tenant_id'])
                ->where('location_id', $context['location']->id)
                ->where(
                'division_id',
                $divisionId
            );
    
        $maintenanceBaseQuery =
            $this->maintenanceQuery($context)
                ->where('tenant_id', $context['tenant_id'])
                ->whereHas(
                'vehicle',
                function ($query) use ($divisionId, $context) {
    
                    $query
                    ->where('tenant_id', $context['tenant_id'])
                    ->where('location_id', $context['location']->id)
                    ->where(
                        'division_id',
                        $divisionId
                    );
    
                }
            );
    
        /*
        |--------------------------------------------------------------------------
        | KPIs GERAIS
        |--------------------------------------------------------------------------
        */
    
        $vehicles =
            (clone $vehiclesQuery)->count();
    
        /*
        |--------------------------------------------------------------------------
        | MANUTENÇÕES — 30 DIAS
        |--------------------------------------------------------------------------
        */
    
        $maintenance30Query =
            (clone $maintenanceBaseQuery)
                ->where(
                    'created_at',
                    '>=',
                    $last30Days
                );
    
        $maintenanceCount30 =
            (clone $maintenance30Query)->count();
    
        $internalMaintenances30 =
            (clone $maintenance30Query)
                ->where(
                    'maintenance_type',
                    'internal'
                )
                ->count();
    
        $externalMaintenances30 =
            (clone $maintenance30Query)
                ->where(
                    'maintenance_type',
                    'external'
                )
                ->count();
    
        $maintenanceCost30 =
            (clone $maintenance30Query)
                ->sum('total_cost');
    
        /*
        |--------------------------------------------------------------------------
        | MÉDIAS DOS ÚLTIMOS 6 MESES
        |--------------------------------------------------------------------------
        */
    
        $maintenance6MonthsCount =
            (clone $maintenanceBaseQuery)
                ->where(
                    'created_at',
                    '>=',
                    $last6Months
                )
                ->count();
    
        $maintenance6MonthsCost =
            (clone $maintenanceBaseQuery)
                ->where(
                    'created_at',
                    '>=',
                    $last6Months
                )
                ->sum('total_cost');
    
        $maintenanceMonthlyAverage =
            $maintenance6MonthsCount / 6;
    
        $costMonthlyAverage =
            $maintenance6MonthsCost / 6;
    
        /*
        |--------------------------------------------------------------------------
        | VARIAÇÕES %
        |--------------------------------------------------------------------------
        */
    
        $maintenanceVariation =
            $maintenanceMonthlyAverage > 0
                ? (
                    (
                        $maintenanceCount30
                        -
                        $maintenanceMonthlyAverage
                    )
                    /
                    $maintenanceMonthlyAverage
                ) * 100
                : 0;
    
        $costVariation =
            $costMonthlyAverage > 0
                ? (
                    (
                        $maintenanceCost30
                        -
                        $costMonthlyAverage
                    )
                    /
                    $costMonthlyAverage
                ) * 100
                : 0;
    
        /*
        |--------------------------------------------------------------------------
        | CUSTO MÉDIO POR MANUTENÇÃO
        |--------------------------------------------------------------------------
        */
    
        $averageMaintenanceCost =
            $maintenanceCount30 > 0
                ? $maintenanceCost30 / $maintenanceCount30
                : 0;
    
        /*
        |--------------------------------------------------------------------------
        | VEÍCULO MAIS CARO
        |--------------------------------------------------------------------------
        */
    
        $criticalVehicle =
            Vehicle::query()
                ->where('tenant_id', $context['tenant_id'])
                ->where('location_id', $context['location']->id)
                ->where(
                'division_id',
                $divisionId
            )
            ->withSum(
                'maintenances',
                'total_cost'
            )
            ->orderByDesc(
                'maintenances_sum_total_cost'
            )
            ->first();
    
        /*
        |--------------------------------------------------------------------------
        | ESTOQUE
        |--------------------------------------------------------------------------
        */
    
        $stockItems =
            StockItem::query()
                ->where('tenant_id', $context['tenant_id'])
                ->where('location_id', $context['location']->id)
                ->count();
    
        return view(
            'reports.index',
            compact(
    
                'vehicles',
    
                'maintenanceCount30',
                'internalMaintenances30',
                'externalMaintenances30',
    
                'maintenanceCost30',
    
                'maintenanceVariation',
                'costVariation',
    
                'averageMaintenanceCost',
    
                'criticalVehicle',
    
                'stockItems'
            )
        );
    }
    public function exportMaintenance(Request $request)
    {
        $context = $this->activeContext();

        if (! $context) {
            return $this->missingActiveContextRedirect();
        }

        $divisionId =
            $context['division']->id;
    
        $startDate =
            $request->start_date;
    
        $endDate =
            $request->end_date;
    
        $maintenances =
            $this->maintenanceQuery($context)->with([
                'vehicle',
                'procedure'
            ])
            ->whereHas(
                'vehicle',
                function ($query) use ($divisionId, $context) {
    
                    $query
                    ->where('tenant_id', $context['tenant_id'])
                    ->where('location_id', $context['location']->id)
                    ->where(
                        'division_id',
                        $divisionId
                    );
    
                }
            )
            ->whereBetween(
                'performed_at',
                [
                    $startDate,
                    $endDate
                ]
            )
            ->latest('performed_at')
            ->get();
    
        /*
        |--------------------------------------------------------------------------
        | KPIs
        |--------------------------------------------------------------------------
        */
    
        $totalCost =
            $maintenances->sum(
                'total_cost'
            );
    
        $internalCount =
            $maintenances
                ->where(
                    'maintenance_type',
                    'internal'
                )
                ->count();
    
        $externalCount =
            $maintenances
                ->where(
                    'maintenance_type',
                    'external'
                )
                ->count();
        $division =
        $context['division'];
        
        $procedureStats =
        $maintenances
            ->groupBy(
                fn($item) =>
                    $item->procedure->name
                    ?? 'Procedimento'
            )
            ->map(function ($items, $procedure) {
    
                $total =
                    $items->sum(
                        'total_cost'
                    );
    
                $count =
                    $items->count();
    
                return [
    
                    'procedure' => $procedure,
    
                    'count' => $count,
    
                    'total' => $total,
    
                    'average' =>
                        $count > 0
                            ? $total / $count
                            : 0
                ];
    
            })
            ->sortByDesc('count');
            
        $vehicleCosts =
        $maintenances
            ->groupBy(
                fn($item) =>
                    $item->vehicle->id
            )
            ->map(function ($items, $vehicleId)
                use ($startDate, $endDate, $context) {
    
                $vehicle =
                    $items->first()->vehicle;
                $startDate =
                    Carbon::parse($startDate)
                        ->startOfDay();
                
                $endDate =
                    Carbon::parse($endDate)
                        ->endOfDay();
                /*
                |--------------------------------------------------------------------------
                | KM RODADOS
                |--------------------------------------------------------------------------
                */
                $kmLogs =
                    $this->vehicleUpdateLogsQuery($vehicleId, $context)->where(
                        'vehicle_id',
                        $vehicleId
                    )
                    ->where(
                        'type',
                        'km'
                    )
                    ->whereBetween(
                        'created_at',
                        [
                            $startDate,
                            $endDate
                        ]
                    )
                    ->get();
                
                $kmDriven = 0;
                
                foreach($kmLogs as $log)
                {
                    $kmDriven += (float) $log->new_value - (float) $log->old_value;
                }
    
                /*
                |--------------------------------------------------------------------------
                | HORAS RODADAS
                |--------------------------------------------------------------------------
                */
    
                $hourLogs =
                    $this->vehicleUpdateLogsQuery($vehicleId, $context)->where(
                        'vehicle_id',
                        $vehicleId
                    )
                    ->where(
                        'type',
                        'hours'
                    )
                    ->whereBetween(
                        'created_at',
                        [
                            $startDate,
                            $endDate
                        ]
                    )
                    ->get();
                
                $hoursDriven = 0;
                
                foreach($hourLogs as $log)
                {
                    $hoursDriven += max(
                        0,
                        (
                            (float) $log->new_value
                            -
                            (float) $log->old_value
                        )
                    );
                }
    
                return [
    
                    'vehicle' =>
                        $vehicle->name
                        ?? 'Veículo',
    
                    'plate' =>
                        $vehicle->plate
                        ?? '-',
    
                    'km_driven' =>
                        $kmDriven,
    
                    'hours_driven' =>
                        $hoursDriven,
    
                    'total' =>
                        $items->sum(
                            'total_cost'
                        )
    
                ];
    
            })
            ->sortByDesc('total');
            
        /*
        |--------------------------------------------------------------------------
        | PDF
        |--------------------------------------------------------------------------
        */
        
        $pdf =
            Pdf::loadView(
                'reports.pdf.maintenance',
                compact(
                    'maintenances',
                    'totalCost',
                    'internalCount',
                    'externalCount',
                    'startDate',
                    'endDate',
                    'division',
                    'procedureStats',
                    'vehicleCosts'
                )
            );
    
        return $pdf->download(
            'relatorio-manutencoes.pdf'
        );
    }
    
    private function activeContext(): ?array
    {
        $user = auth()->user();
        $divisionId = session('active_division_id');

        if (! $user || ! $divisionId) {
            return null;
        }

        $division = Division::query()
            ->where('tenant_id', $user->tenant_id)
            ->find($divisionId);

        $location = app(ActiveContextService::class)
            ->activeLocation($user);

        if (
            ! $division
            || ! $location
            || (int) $location->division_id !== (int) $division->id
            || (int) $location->tenant_id !== (int) $user->tenant_id
        ) {
            return null;
        }

        return [
            'user' => $user,
            'tenant_id' => (int) $user->tenant_id,
            'division' => $division,
            'location' => $location,
        ];
    }

    private function maintenanceQuery(array $context)
    {
        return MaintenanceRecord::query()
            ->where('tenant_id', $context['tenant_id'])
            ->whereHas('vehicle', function ($query) use ($context) {
                $query
                    ->where('tenant_id', $context['tenant_id'])
                    ->where('division_id', $context['division']->id)
                    ->where('location_id', $context['location']->id);
            });
    }

    private function vehicleUpdateLogsQuery(int $vehicleId, array $context)
    {
        return VehicleUpdateLog::query()
            ->where('vehicle_id', $vehicleId)
            ->where('division_id', $context['division']->id)
            ->where('location_id', $context['location']->id);
    }

    private function missingActiveContextRedirect()
    {
        return redirect()
            ->route('portal')
            ->with('warning', 'Selecione uma divisão e uma unidade para continuar.');
    }

    private function buildMaintenanceReportData($request, array $context)
    {
        $startDate =
            Carbon::parse(
                $request->start_date
            )->startOfDay();
    
        $endDate =
            Carbon::parse(
                $request->end_date
            )->endOfDay();
    
        $maintenances =
            $this->maintenanceQuery($context)->with([
                'vehicle',
                'procedure'
            ])
            ->whereBetween(
                'performed_at',
                [$startDate, $endDate]
            )
            ->orderBy(
                'performed_at',
                'desc'
            )
            ->get();
    
        $internalCount =
            $maintenances
                ->where(
                    'maintenance_type',
                    'internal'
                )
                ->count();
    
        $externalCount =
            $maintenances
                ->where(
                    'maintenance_type',
                    'external'
                )
                ->count();
    
        $totalCost =
            $maintenances->sum(
                'total_cost'
            );
    
        $procedureStats =
            $maintenances
                ->groupBy(
                    fn($maintenance) =>
                        $maintenance->procedure->name ?? 'Sem procedimento'
                )
                ->map(function ($items, $procedure) {
    
                    return [
    
                        'procedure' => $procedure,
    
                        'count' =>
                            $items->count(),
    
                        'total' =>
                            $items->sum('total_cost'),
    
                        'average' =>
                            $items->count() > 0
                                ? $items->sum('total_cost') / $items->count()
                                : 0
    
                    ];
    
                })
                ->sortByDesc('count')
                ->values();
    
        $vehicleCosts =
            $maintenances
                ->groupBy('vehicle_id')
                ->map(function ($items, $vehicleId) use ($startDate, $endDate, $context) {
    
                    $vehicle =
                        $items->first()->vehicle;
    
                    $kmLogs =
                        $this->vehicleUpdateLogsQuery($vehicleId, $context)->where(
                            'vehicle_id',
                            $vehicleId
                        )
                        ->where(
                            'type',
                            'km'
                        )
                        ->whereBetween(
                            'created_at',
                            [$startDate, $endDate]
                        )
                        ->get();
    
                    $hoursLogs =
                        $this->vehicleUpdateLogsQuery($vehicleId, $context)->where(
                            'vehicle_id',
                            $vehicleId
                        )
                        ->where(
                            'type',
                            'hours'
                        )
                        ->whereBetween(
                            'created_at',
                            [$startDate, $endDate]
                        )
                        ->get();
    
                    return [
    
                        'vehicle' =>
                            $vehicle->name ?? '-',
    
                        'plate' =>
                            $vehicle->plate ?? '-',
    
                        'km_driven' =>
                            $kmLogs->sum(function ($log) {
    
                                return
                                    max(
                                        0,
                                        ($log->new_value ?? 0)
                                        -
                                        ($log->old_value ?? 0)
                                    );
    
                            }),
    
                        'hours_driven' =>
                            $hoursLogs->sum(function ($log) {
    
                                return
                                    max(
                                        0,
                                        ($log->new_value ?? 0)
                                        -
                                        ($log->old_value ?? 0)
                                    );
    
                            }),
    
                        'total' =>
                            $items->sum('total_cost')
    
                    ];
    
                })
                ->sortByDesc('total')
                ->values();
    
        return [
    
            'startDate' => $startDate,
            'endDate' => $endDate,
    
            'maintenances_raw' =>
                $maintenances,
            
            'maintenances' =>
                $maintenances
                    ->map(function ($m) {
            
                        return [
            
                            'created_at' =>
                                $m->created_at,
            
                            'vehicle_name' =>
                                $m->vehicle->name
                                ?? '-',
            
                            'vehicle_plate' =>
                                $m->vehicle->plate
                                ?? '-',
            
                            'procedure_name' =>
                                $m->procedure->name
                                ?? '-',
            
                            'maintenance_type' =>
                                $m->maintenance_type,
            
                            'total_cost' =>
                                $m->total_cost,
            
                        ];
            
                    })
                    ->toArray(),
            'internalCount' => $internalCount,
            'externalCount' => $externalCount,
    
            'totalCost' => $totalCost,
    
            'procedureStats' => $procedureStats->toArray(),    
            'vehicleCosts' => $vehicleCosts->toArray(),    
            'division' =>
                $context['division']
    
        ];
    }
    public function exportMaintenanceExcel(Request $request)
    {
        $context = $this->activeContext();

        if (! $context) {
            return $this->missingActiveContextRedirect();
        }

        $data = $this->buildMaintenanceReportData($request, $context);
    
        return Excel::download(
    
            new MaintenanceReportExport($data),
    
            'relatorio-manutencoes.xlsx'
        );
    }
}
