<?php

namespace App\Services;

use App\Models\FuelFilling;
use App\Models\MaintenanceRecord;
use App\Models\User;
use App\Models\VehicleDowntimePeriod;
use App\Services\Permissions\ProfilePermissionService;
use App\Services\Reports\ReportContextService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class OperationalDashboardService
{
    public function __construct(
        private readonly ReportContextService $reportContext,
        private readonly ProfilePermissionService $permissions
    ) {
    }

    public function indicators(User $user): array
    {
        $context = $this->reportContext->resolve($user);

        if (! $context) {
            return $this->emptyIndicators();
        }

        $permissionScope = [
            'tenant_id' => $context['tenant_id'],
            'division_id' => $context['division']->id,
            'location_id' => $context['location']->id,
            'module' => 'fleet',
        ];
        $canViewCosts = $this->permissions->allows($user, 'fuel.view_costs', $permissionScope)
            && $this->permissions->allows($user, 'maintenance.view_costs', $permissionScope);
        $recentFillings = $this->recentFuelFillings($context);

        return [
            'fuel_consumption_ranking' => $this->fuelConsumptionRanking($recentFillings, $canViewCosts),
            'vehicle_fuel_averages' => $this->vehicleFuelAverages($recentFillings),
            'longest_stopped_vehicles' => $this->longestStoppedVehicles($context),
            'six_month_cost_series' => $canViewCosts
                ? $this->sixMonthCostSeries($context)
                : [],
            'can_view_dashboard_costs' => $canViewCosts,
        ];
    }

    private function recentFuelFillings(array $context): Collection
    {
        return FuelFilling::query()
            ->with(['vehicle:id,name,plate,asset_code', 'product:id,name'])
            ->where('tenant_id', $context['tenant_id'])
            ->where('division_id', $context['division']->id)
            ->where('location_id', $context['location']->id)
            ->whereNull('cancelled_at')
            ->whereBetween('filled_at', [now()->subDays(30)->startOfDay(), now()])
            ->whereNotNull('vehicle_id')
            ->orderByDesc('filled_at')
            ->get();
    }

    private function fuelConsumptionRanking(Collection $fillings, bool $canViewCosts): array
    {
        return $fillings
            ->groupBy('vehicle_id')
            ->map(function ($items) use ($canViewCosts) {
                $first = $items->first();
                $products = $items
                    ->groupBy('fuel_product_id')
                    ->map(fn ($productItems) => [
                        'name' => $productItems->first()?->product?->name ?? 'Produto não informado',
                        'liters' => (float) $productItems->sum('quantity_liters'),
                    ])
                    ->sortByDesc('liters')
                    ->values();

                return [
                    'vehicle_id' => (int) $first->vehicle_id,
                    'vehicle_label' => $this->vehicleLabel($first->vehicle),
                    'product' => $products->first()['name'] ?? 'Produto não informado',
                    'has_multiple_products' => $products->count() > 1,
                    'liters' => round((float) $items->sum('quantity_liters'), 1),
                    'total_cost' => $canViewCosts
                        ? round((float) $items->sum('total_cost'), 2)
                        : null,
                ];
            })
            ->sortByDesc('liters')
            ->take(5)
            ->values()
            ->all();
    }

    private function vehicleFuelAverages(Collection $fillings): array
    {
        return $fillings
            ->groupBy('vehicle_id')
            ->map(function (Collection $vehicleFillings) {
                $recent = $vehicleFillings
                    ->sortByDesc('filled_at')
                    ->take(5)
                    ->sortBy('filled_at')
                    ->values();
                $kmAverage = $this->averageByCounter($recent->filter(fn (FuelFilling $filling) => $filling->is_km_reading_usable)->values(), 'vehicle_km');

                if ($kmAverage['value'] !== null) {
                    return $this->fuelAveragePayload($kmAverage['value'], 'km/L', 'available');
                }

                $hoursAverage = $this->averageByCounter($recent, 'vehicle_hours');

                if ($hoursAverage['value'] !== null) {
                    return $this->fuelAveragePayload($hoursAverage['value'], 'h/L', 'available');
                }

                $hasInconsistentData = $kmAverage['has_inconsistency']
                    || $hoursAverage['has_inconsistency'];

                return [
                    'value' => null,
                    'formatted' => 'N/D',
                    'unit' => null,
                    'status' => $hasInconsistentData ? 'inconsistent' : 'unavailable',
                    'title' => $hasInconsistentData
                        ? 'Leituras insuficientes ou inconsistentes no período'
                        : 'São necessários ao menos dois abastecimentos com leitura válida',
                ];
            })
            ->all();
    }

    private function averageByCounter(Collection $fillings, string $counterField): array
    {
        $totalDelta = 0.0;
        $totalLiters = 0.0;
        $hasInconsistency = false;

        for ($index = 1; $index < $fillings->count(); $index++) {
            $previous = $fillings->get($index - 1);
            $current = $fillings->get($index);
            $previousCounter = $previous->{$counterField};
            $currentCounter = $current->{$counterField};

            if ($previousCounter === null || $currentCounter === null) {
                continue;
            }

            $liters = (float) $current->quantity_liters;
            $delta = (float) $currentCounter - (float) $previousCounter;

            if ($delta <= 0 || $liters <= 0) {
                $hasInconsistency = true;
                continue;
            }

            $totalDelta += $delta;
            $totalLiters += $liters;
        }

        return [
            'value' => $totalLiters > 0 ? round($totalDelta / $totalLiters, 2) : null,
            'has_inconsistency' => $hasInconsistency,
        ];
    }

    private function fuelAveragePayload(float $value, string $unit, string $status): array
    {
        return [
            'value' => $value,
            'formatted' => number_format($value, 1, ',', '.').' '.$unit,
            'unit' => $unit,
            'status' => $status,
            'title' => 'Média calculada com abastecimentos válidos dos últimos 30 dias',
        ];
    }

    private function longestStoppedVehicles(array $context): array
    {
        $vehicles = $this->reportContext
            ->vehicleQuery($context)
            ->whereIn('operational_status', ['maintenance', 'inactive'])
            ->with(['activeMaintenances' => fn ($query) => $query
                ->select(['id', 'vehicle_id', 'reason', 'started_at'])
                ->whereNull('cancelled_at')
                ->latest('started_at')])
            ->get(['id', 'name', 'plate', 'asset_code', 'operational_status', 'status_changed_at']);

        $openPeriods = VehicleDowntimePeriod::query()
            ->whereIn('vehicle_id', $vehicles->pluck('id'))
            ->whereNull('ended_at')
            ->latest('started_at')
            ->get()
            ->unique('vehicle_id')
            ->keyBy('vehicle_id');

        return $vehicles
            ->map(function ($vehicle) use ($openPeriods) {
                $period = $openPeriods->get($vehicle->id);
                $startedAt = $period?->started_at ?? $vehicle->status_changed_at;
                $maintenance = $vehicle->activeMaintenances->first();

                return [
                    'vehicle_id' => (int) $vehicle->id,
                    'vehicle_label' => $this->vehicleLabel($vehicle),
                    'status' => $vehicle->operational_status === 'maintenance'
                        ? 'Em manutenção'
                        : 'Inativo',
                    'days' => $startedAt
                        ? (int) $startedAt->copy()->startOfDay()->diffInDays(now()->startOfDay())
                        : null,
                    'started_at' => $startedAt?->format('d/m/Y'),
                    'reason' => $period?->reason ?? $maintenance?->reason,
                ];
            })
            ->sortByDesc(fn (array $item) => $item['days'] ?? -1)
            ->take(5)
            ->values()
            ->all();
    }

    private function sixMonthCostSeries(array $context): array
    {
        $months = collect(range(5, 0))
            ->map(fn (int $monthsAgo) => now()->startOfMonth()->subMonths($monthsAgo));
        $start = $months->first()->copy()->startOfMonth();
        $end = now()->endOfMonth();

        $fuelCosts = FuelFilling::query()
            ->where('tenant_id', $context['tenant_id'])
            ->where('division_id', $context['division']->id)
            ->where('location_id', $context['location']->id)
            ->whereNull('cancelled_at')
            ->whereBetween('filled_at', [$start, $end])
            ->get(['filled_at', 'total_cost'])
            ->groupBy(fn (FuelFilling $filling) => $filling->filled_at->format('Y-m'));

        $maintenanceCosts = $this->reportContext
            ->maintenanceQuery($context)
            ->whereNull('deleted_at')
            ->whereBetween('performed_at', [$start->toDateString(), $end->toDateString()])
            ->get(['performed_at', 'total_cost'])
            ->groupBy(fn (MaintenanceRecord $maintenance) => $maintenance->performed_at->format('Y-m'));

        $series = $months->map(function (Carbon $month) use ($fuelCosts, $maintenanceCosts) {
            $key = $month->format('Y-m');

            return [
                'key' => $key,
                'label' => ucfirst($month->locale('pt_BR')->translatedFormat('M')),
                'fuel' => round((float) ($fuelCosts->get($key)?->sum('total_cost') ?? 0), 2),
                'maintenance' => round((float) ($maintenanceCosts->get($key)?->sum('total_cost') ?? 0), 2),
            ];
        });
        $maximum = max(1, (float) $series->max(fn (array $month) => max($month['fuel'], $month['maintenance'])));

        return $series
            ->map(fn (array $month) => $month + [
                'fuel_percent' => round(($month['fuel'] / $maximum) * 100, 1),
                'maintenance_percent' => round(($month['maintenance'] / $maximum) * 100, 1),
            ])
            ->all();
    }

    private function vehicleLabel($vehicle): string
    {
        return $vehicle?->asset_code
            ?: $vehicle?->plate
            ?: $vehicle?->name
            ?: 'Veículo sem identificação';
    }

    private function emptyIndicators(): array
    {
        return [
            'fuel_consumption_ranking' => [],
            'vehicle_fuel_averages' => [],
            'longest_stopped_vehicles' => [],
            'six_month_cost_series' => [],
            'can_view_dashboard_costs' => false,
        ];
    }
}
