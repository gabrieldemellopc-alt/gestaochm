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
    private const MAX_PLAUSIBLE_KM_PER_LITER = 20.0;

    public function __construct(
        private readonly ReportContextService $reportContext,
        private readonly ProfilePermissionService $permissions
    ) {
    }

    public function indicators(User $user, string $fleetRelation = 'all'): array
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
        $recentFillings = $this->recentFuelFillings($context, $fleetRelation);

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

    private function recentFuelFillings(array $context, string $fleetRelation): Collection
    {
        return FuelFilling::query()
            ->with([
                'vehicle:id,name,plate,asset_code,type,fleet_relation,km_control_enabled,hours_control_enabled,tire_control_enabled',
                'product:id,name',
                'vehicleReadingLogs:id,fuel_filling_id,type,reading_status',
            ])
            ->where('tenant_id', $context['tenant_id'])
            ->where('division_id', $context['division']->id)
            ->where('location_id', $context['location']->id)
            ->whereNull('cancelled_at')
            ->whereBetween('filled_at', [now()->subDays(30)->startOfDay(), now()])
            ->whereNotNull('vehicle_id')
            ->when($fleetRelation !== 'all', fn ($query) => $query->whereHas('vehicle', fn ($vehicles) => $vehicles->where('fleet_relation', $fleetRelation)))
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
                $firstFilling = $recent->first();
                $vehicle = $firstFilling?->relationLoaded('vehicle')
                    ? $firstFilling->vehicle
                    : null;

                if ($vehicle && ! $vehicle->km_control_enabled && $vehicle->hours_control_enabled) {
                    $hoursAverage = $this->averageLitersPerHour($recent);

                    return $hoursAverage['value'] !== null
                        ? $this->fuelAveragePayload($hoursAverage['value'], 'L/H', 'available')
                        : $this->unavailableFuelAverage($hoursAverage['has_inconsistency']);
                }

                if ($vehicle && ! $vehicle->km_control_enabled) {
                    return $this->unavailableFuelAverage(false);
                }

                // Keep every recent filling in sequence. Filtering first would join two
                // otherwise valid readings across an invalid historical reading.
                $kmAverage = $this->averageByCounter($recent, 'vehicle_km');

                if ($kmAverage['value'] !== null) {
                    return $this->fuelAveragePayload($kmAverage['value'], 'km/L', 'available');
                }

                return $this->unavailableFuelAverage($kmAverage['has_inconsistency']);
            })
            ->all();
    }

    private function unavailableFuelAverage(bool $hasInconsistency): array
    {
        return [
            'value' => null,
            'formatted' => 'N/D',
            'unit' => null,
            'status' => $hasInconsistency ? 'inconsistent' : 'unavailable',
            'title' => $hasInconsistency
                ? 'Leituras insuficientes ou inconsistentes no período'
                : 'São necessários ao menos dois abastecimentos com leitura válida',
        ];
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

            if ($counterField === 'vehicle_km'
                && (! $this->hasUsableKmForConsumption($previous)
                    || ! $this->hasUsableKmForConsumption($current))) {
                $hasInconsistency = true;
                continue;
            }

            if ($previousCounter === null || $currentCounter === null) {
                $hasInconsistency = true;
                continue;
            }

            $liters = (float) $current->quantity_liters;
            $delta = (float) $currentCounter - (float) $previousCounter;

            if ($delta <= 0 || $liters <= 0) {
                $hasInconsistency = true;
                continue;
            }

            if ($counterField === 'vehicle_km' && ($delta / $liters) > self::MAX_PLAUSIBLE_KM_PER_LITER) {
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

    private function hasUsableKmForConsumption(FuelFilling $filling): bool
    {
        // Respect the existing valid/suspect/ignored status, while excluding the
        // historical sentinel values 0 and 1 from statistical consumption only.
        return $filling->is_km_reading_usable && (float) $filling->vehicle_km > 1.0;
    }

    private function averageLitersPerHour(Collection $fillings): array
    {
        $totalHours = 0.0;
        $totalLiters = 0.0;
        $hasInconsistency = false;

        for ($index = 1; $index < $fillings->count(); $index++) {
            $previous = $fillings->get($index - 1);
            $current = $fillings->get($index);

            if (! $this->hasUsableHoursForConsumption($previous)
                || ! $this->hasUsableHoursForConsumption($current)) {
                $hasInconsistency = true;
                continue;
            }

            $deltaHours = (float) $current->vehicle_hours - (float) $previous->vehicle_hours;
            $liters = (float) $current->quantity_liters;

            if ($deltaHours <= 0 || $liters <= 0) {
                $hasInconsistency = true;
                continue;
            }

            $totalHours += $deltaHours;
            $totalLiters += $liters;
        }

        return [
            'value' => $totalHours > 0 ? round($totalLiters / $totalHours, 2) : null,
            'has_inconsistency' => $hasInconsistency,
        ];
    }

    private function hasUsableHoursForConsumption(FuelFilling $filling): bool
    {
        if ($filling->vehicle_hours === null || (float) $filling->vehicle_hours <= 0) {
            return false;
        }

        $hoursLog = $filling->relationLoaded('vehicleReadingLogs')
            ? $filling->vehicleReadingLogs->firstWhere('type', 'hours')
            : null;

        return $hoursLog === null || $hoursLog->is_reading_usable;
    }

    private function fuelAveragePayload(float $value, string $unit, string $status): array
    {
        return [
            'value' => $value,
            'formatted' => number_format($value, 1, ',', '.').' '.$unit,
            'unit' => $unit,
            'status' => $status,
            'title' => 'Média baseada no histórico de abastecimentos dos últimos 30 dias; não utiliza o contador operacional atual.',
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
            ->get(['filled_at', 'total_cost', 'source_total_cost'])
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
                'fuel' => round((float) ($fuelCosts->get($key)?->sum('reporting_total_cost') ?? 0), 2),
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
