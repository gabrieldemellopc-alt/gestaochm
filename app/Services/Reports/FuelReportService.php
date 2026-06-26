<?php

namespace App\Services\Reports;

use App\Models\FuelFilling;
use App\Models\FuelMovement;
use App\Models\FuelProduct;
use App\Models\FuelReceipt;
use App\Models\FuelTank;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class FuelReportService
{
    public function __construct(
        private readonly ReportContextService $reportContext
    ) {
    }

    public function build(array $filters = [], ?array $context = null): array
    {
        $context ??= $this->reportContext->resolve();

        if (! $context) {
            return [
                'context' => null,
                'applied_filters' => [],
                'error' => 'Contexto ativo de divisao/unidade nao encontrado.',
            ];
        }

        $filters = $this->filters($filters, $context);
        $tankSummary = $this->tankSummary($context, $filters);
        $receipts = $this->receiptsPeriod($context, $filters);
        $fillings = $this->fillingsPeriod($context, $filters);
        $movements = $this->movementsPeriod($context, $filters);
        $consumptionByVehicle = $this->consumptionByVehicle($fillings);

        if ($filters['only_vehicles_with_consumption']) {
            $consumptionByVehicle = $consumptionByVehicle
                ->filter(fn (array $row) => $row['status'] === 'calculado')
                ->values();
        }

        return [
            'context' => $context,
            'applied_filters' => $filters,
            'vehicles' => $this->vehicles($context),
            'products' => $this->products($context),
            'tanks' => $this->tanks($context),
            'tank_summary' => $tankSummary,
            'product_balances' => $this->productBalances($tankSummary),
            'low_tanks' => $tankSummary
                ->filter(fn (array $tank) => $tank['status'] === 'low')
                ->values(),
            'receipts_period' => $receipts,
            'fillings_period' => $fillings,
            'movements_period' => $movements,
            'total_received_liters' => $this->sumDecimal($receipts, 'quantity_liters'),
            'total_filled_liters' => $this->sumDecimal($fillings, 'quantity_liters'),
            'total_received_cost' => $this->sumDecimal($receipts, 'total_cost'),
            'total_filled_cost' => $this->sumDecimal($fillings, 'total_cost'),
            'average_cost_per_liter' => $this->averageCostPerLiter($receipts, $fillings),
            'vehicles_filled_count' => $fillings->pluck('vehicle_id')->filter()->unique()->count(),
            'fillings_without_km_hr' => $fillings
                ->filter(fn (FuelFilling $filling) => $filling->vehicle_km === null && $filling->vehicle_hours === null)
                ->values(),
            'consumption_by_vehicle' => $consumptionByVehicle,
            'top_consumption_vehicles' => $consumptionByVehicle
                ->sortByDesc('total_liters')
                ->take(5)
                ->values(),
            'latest_receipts' => $this->latestReceipts($context, $filters),
            'latest_fillings' => $this->latestFillings($context, $filters),
            'cancelled_records' => $this->cancelledRecords($context, $filters),
        ];
    }

    private function filters(array $filters, array $context): array
    {
        $startDate = ! empty($filters['start_date'])
            ? Carbon::parse($filters['start_date'])->startOfDay()
            : Carbon::now()->subDays(30)->startOfDay();

        $endDate = ! empty($filters['end_date'])
            ? Carbon::parse($filters['end_date'])->endOfDay()
            : Carbon::now()->endOfDay();

        $periodIsValid = $startDate->lte($endDate);

        return [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'period_is_valid' => $periodIsValid,
            'period_error' => $periodIsValid
                ? null
                : 'A data inicial nao pode ser maior que a data final.',
            'vehicle_id' => $this->validVehicleId($filters['vehicle_id'] ?? null, $context),
            'fuel_product_id' => $this->positiveInteger($filters['fuel_product_id'] ?? $filters['product_id'] ?? null),
            'fuel_tank_id' => $this->validTankId($filters['fuel_tank_id'] ?? null, $context),
            'event_type' => $this->eventType($filters['event_type'] ?? null),
            'only_low_tanks' => filter_var($filters['only_low_tanks'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'only_vehicles_with_consumption' => filter_var($filters['only_vehicles_with_consumption'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'include_cancelled' => $context['can_view_cancelled']
                && filter_var($filters['include_cancelled'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'include_missing_counters' => filter_var($filters['include_missing_counters'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ];
    }

    private function tankSummary(array $context, array $filters): Collection
    {
        $query = FuelTank::query()
            ->with(['product'])
            ->where('tenant_id', $context['tenant_id'])
            ->where('division_id', $context['division']->id)
            ->where('location_id', $context['location']->id)
            ->orderByDesc('active')
            ->orderBy('name');

        if ($filters['fuel_product_id']) {
            $query->where('fuel_product_id', $filters['fuel_product_id']);
        }

        if ($filters['fuel_tank_id']) {
            $query->where('id', $filters['fuel_tank_id']);
        }

        return $query
            ->get()
            ->map(function (FuelTank $tank) use ($context) {
                $capacity = (float) $tank->capacity_liters;
                $balance = (float) $tank->current_balance_liters;
                $minimum = (float) $tank->minimum_balance_liters;

                return [
                    'tank' => $tank,
                    'product' => $tank->product,
                    'capacity_liters' => $capacity,
                    'current_balance_liters' => $balance,
                    'occupied_percentage' => $capacity > 0 ? round(($balance / $capacity) * 100, 1) : 0,
                    'minimum_balance_liters' => $minimum,
                    'status' => ! $tank->active ? 'inactive' : ($balance <= $minimum ? 'low' : 'normal'),
                    'last_receipt' => $this->lastReceiptForTank($tank, $context),
                    'last_filling' => $this->lastFillingForTank($tank, $context),
                ];
            })
            ->when($filters['only_low_tanks'], fn (Collection $items) => $items
                ->filter(fn (array $item) => $item['status'] === 'low')
                ->values());
    }

    private function productBalances(Collection $tankSummary): Collection
    {
        return $tankSummary
            ->groupBy(fn (array $tank) => $tank['product']?->slug ?: mb_strtolower((string) $tank['product']?->name))
            ->map(function (Collection $items) {
                $first = $items->first();

                return [
                    'product' => $first['product'],
                    'name' => $first['product']?->name ?? 'Produto',
                    'slug' => $first['product']?->slug,
                    'balance_liters' => round($items->sum('current_balance_liters'), 3),
                    'capacity_liters' => round($items->sum('capacity_liters'), 3),
                    'minimum_balance_liters' => round($items->sum('minimum_balance_liters'), 3),
                    'tanks_count' => $items->count(),
                ];
            })
            ->values();
    }

    private function vehicles(array $context): Collection
    {
        return Vehicle::query()
            ->where('tenant_id', $context['tenant_id'])
            ->where('division_id', $context['division']->id)
            ->where('location_id', $context['location']->id)
            ->orderBy('name')
            ->get(['id', 'name', 'plate']);
    }

    private function products(array $context): Collection
    {
        return FuelProduct::query()
            ->where('tenant_id', $context['tenant_id'])
            ->where('active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);
    }

    private function tanks(array $context): Collection
    {
        return FuelTank::query()
            ->with('product')
            ->where('tenant_id', $context['tenant_id'])
            ->where('division_id', $context['division']->id)
            ->where('location_id', $context['location']->id)
            ->orderByDesc('active')
            ->orderBy('name')
            ->get(['id', 'name', 'fuel_product_id', 'active']);
    }

    private function receiptsPeriod(array $context, array $filters): Collection
    {
        if (! $filters['period_is_valid']) {
            return collect();
        }

        $query = FuelReceipt::query()
            ->with(['tank.product', 'product', 'responsible'])
            ->where('tenant_id', $context['tenant_id'])
            ->where('division_id', $context['division']->id)
            ->where('location_id', $context['location']->id)
            ->whereNull('cancelled_at')
            ->whereBetween('received_at', [$filters['start_date'], $filters['end_date']]);

        $this->applyFuelFilters($query, $filters);

        return $query->latest('received_at')->get();
    }

    private function fillingsPeriod(array $context, array $filters): Collection
    {
        if (! $filters['period_is_valid']) {
            return collect();
        }

        $query = FuelFilling::query()
            ->with(['tank.product', 'product', 'vehicle', 'driver', 'responsible'])
            ->where('tenant_id', $context['tenant_id'])
            ->where('division_id', $context['division']->id)
            ->where('location_id', $context['location']->id)
            ->whereNull('cancelled_at')
            ->whereBetween('filled_at', [$filters['start_date'], $filters['end_date']]);

        $this->applyFuelFilters($query, $filters);

        if ($filters['vehicle_id']) {
            $query->where('vehicle_id', $filters['vehicle_id']);
        }

        if (! $filters['include_missing_counters']) {
            // Do not remove the filling from totals; this flag is for future views.
        }

        return $query->latest('filled_at')->get();
    }

    private function movementsPeriod(array $context, array $filters): Collection
    {
        if (! $filters['period_is_valid']) {
            return collect();
        }

        $query = FuelMovement::query()
            ->with(['tank.product', 'product', 'responsible'])
            ->where('tenant_id', $context['tenant_id'])
            ->where('division_id', $context['division']->id)
            ->where('location_id', $context['location']->id)
            ->whereBetween('created_at', [$filters['start_date'], $filters['end_date']]);

        $this->applyFuelFilters($query, $filters);

        if ($filters['event_type']) {
            $query->where('movement_type', $filters['event_type']);
        }

        return $query->latest('created_at')->get();
    }

    private function consumptionByVehicle(Collection $fillings): Collection
    {
        return $fillings
            ->groupBy(fn (FuelFilling $filling) => $filling->vehicle_id.'-'.$filling->fuel_product_id)
            ->map(function (Collection $items) {
                /** @var FuelFilling $first */
                $first = $items->sortBy('filled_at')->first();
                $kmResult = $this->counterConsumption($items, 'vehicle_km');
                $hoursResult = $this->counterConsumption($items, 'vehicle_hours');

                return [
                    'vehicle' => $first->vehicle,
                    'product' => $first->product,
                    'vehicle_id' => $first->vehicle_id,
                    'fuel_product_id' => $first->fuel_product_id,
                    'fillings_count' => $items->count(),
                    'total_liters' => $this->sumDecimal($items, 'quantity_liters'),
                    'total_cost' => $this->sumDecimal($items, 'total_cost'),
                    'km_consumption' => $kmResult,
                    'hours_consumption' => $hoursResult,
                    'status' => $this->combinedConsumptionStatus($kmResult, $hoursResult),
                ];
            })
            ->filter(fn (array $row) => $row['vehicle_id'])
            ->values();
    }

    private function counterConsumption(Collection $items, string $counterField): array
    {
        $valid = $items
            ->filter(fn (FuelFilling $filling) => $filling->{$counterField} !== null)
            ->sortBy('filled_at')
            ->values();

        if ($valid->count() < 2) {
            return [
                'status' => 'dados_insuficientes',
                'initial' => null,
                'final' => null,
                'delta' => null,
                'liters' => $this->sumDecimal($items, 'quantity_liters'),
                'value' => null,
            ];
        }

        $previous = null;
        foreach ($valid as $filling) {
            $current = (float) $filling->{$counterField};

            if ($previous !== null && $current <= $previous) {
                return [
                    'status' => $counterField === 'vehicle_km' ? 'km_invalido' : 'horas_invalidas',
                    'initial' => (float) $valid->first()->{$counterField},
                    'final' => (float) $valid->last()->{$counterField},
                    'delta' => null,
                    'liters' => $this->sumDecimal($items, 'quantity_liters'),
                    'value' => null,
                ];
            }

            $previous = $current;
        }

        $initial = (float) $valid->first()->{$counterField};
        $final = (float) $valid->last()->{$counterField};
        $delta = $final - $initial;

        if ($delta <= 0) {
            return [
                'status' => $counterField === 'vehicle_km' ? 'km_invalido' : 'horas_invalidas',
                'initial' => $initial,
                'final' => $final,
                'delta' => null,
                'liters' => $this->sumDecimal($items, 'quantity_liters'),
                'value' => null,
            ];
        }

        $liters = $this->sumDecimal($items, 'quantity_liters');

        return [
            'status' => 'calculado',
            'initial' => $initial,
            'final' => $final,
            'delta' => $delta,
            'liters' => $liters,
            'value' => $counterField === 'vehicle_km'
                ? round($delta / max($liters, 0.001), 3)
                : round($liters / $delta, 3),
        ];
    }

    private function cancelledRecords(array $context, array $filters): Collection
    {
        if (! $filters['include_cancelled'] || ! $filters['period_is_valid']) {
            return collect();
        }

        return collect()
            ->merge(
                FuelReceipt::query()
                    ->with(['tank.product', 'product', 'canceller'])
                    ->where('tenant_id', $context['tenant_id'])
                    ->where('division_id', $context['division']->id)
                    ->where('location_id', $context['location']->id)
                    ->whereNotNull('cancelled_at')
                    ->whereBetween('cancelled_at', [$filters['start_date'], $filters['end_date']])
                    ->get()
                    ->map(fn (FuelReceipt $receipt) => [
                        'date' => $receipt->cancelled_at,
                        'type' => 'Recebimento cancelado',
                        'record' => 'Recebimento #'.$receipt->id,
                        'quantity_liters' => (float) $receipt->quantity_liters,
                        'total_cost' => (float) $receipt->total_cost,
                        'reason' => $receipt->cancel_reason,
                        'user' => $receipt->canceller?->name,
                    ])
            )
            ->merge(
                FuelFilling::query()
                    ->with(['tank.product', 'product', 'vehicle', 'canceller'])
                    ->where('tenant_id', $context['tenant_id'])
                    ->where('division_id', $context['division']->id)
                    ->where('location_id', $context['location']->id)
                    ->whereNotNull('cancelled_at')
                    ->whereBetween('cancelled_at', [$filters['start_date'], $filters['end_date']])
                    ->get()
                    ->map(fn (FuelFilling $filling) => [
                        'date' => $filling->cancelled_at,
                        'type' => 'Abastecimento cancelado',
                        'record' => ($filling->vehicle?->plate ?? 'Veiculo') . ' #'.$filling->id,
                        'quantity_liters' => (float) $filling->quantity_liters,
                        'total_cost' => (float) $filling->total_cost,
                        'reason' => $filling->cancel_reason,
                        'user' => $filling->canceller?->name,
                    ])
            )
            ->sortByDesc('date')
            ->values();
    }

    private function latestReceipts(array $context, array $filters): Collection
    {
        $query = FuelReceipt::query()
            ->with(['tank.product', 'product', 'responsible'])
            ->where('tenant_id', $context['tenant_id'])
            ->where('division_id', $context['division']->id)
            ->where('location_id', $context['location']->id)
            ->whereNull('cancelled_at');

        $this->applyFuelFilters($query, $filters);

        return $query->latest('received_at')->limit(5)->get();
    }

    private function latestFillings(array $context, array $filters): Collection
    {
        $query = FuelFilling::query()
            ->with(['tank.product', 'product', 'vehicle', 'driver', 'responsible'])
            ->where('tenant_id', $context['tenant_id'])
            ->where('division_id', $context['division']->id)
            ->where('location_id', $context['location']->id)
            ->whereNull('cancelled_at');

        $this->applyFuelFilters($query, $filters);

        if ($filters['vehicle_id']) {
            $query->where('vehicle_id', $filters['vehicle_id']);
        }

        return $query->latest('filled_at')->limit(5)->get();
    }

    private function lastReceiptForTank(FuelTank $tank, array $context): ?FuelReceipt
    {
        return FuelReceipt::query()
            ->where('tenant_id', $context['tenant_id'])
            ->where('division_id', $context['division']->id)
            ->where('location_id', $context['location']->id)
            ->where('fuel_tank_id', $tank->id)
            ->whereNull('cancelled_at')
            ->latest('received_at')
            ->first();
    }

    private function lastFillingForTank(FuelTank $tank, array $context): ?FuelFilling
    {
        return FuelFilling::query()
            ->where('tenant_id', $context['tenant_id'])
            ->where('division_id', $context['division']->id)
            ->where('location_id', $context['location']->id)
            ->where('fuel_tank_id', $tank->id)
            ->whereNull('cancelled_at')
            ->latest('filled_at')
            ->first();
    }

    private function applyFuelFilters(Builder $query, array $filters): void
    {
        if ($filters['fuel_product_id']) {
            $query->where('fuel_product_id', $filters['fuel_product_id']);
        }

        if ($filters['fuel_tank_id']) {
            $query->where('fuel_tank_id', $filters['fuel_tank_id']);
        }
    }

    private function validVehicleId(mixed $vehicleId, array $context): ?int
    {
        $vehicleId = $this->positiveInteger($vehicleId);

        if (! $vehicleId) {
            return null;
        }

        return Vehicle::query()
            ->where('tenant_id', $context['tenant_id'])
            ->where('division_id', $context['division']->id)
            ->where('location_id', $context['location']->id)
            ->where('id', $vehicleId)
            ->exists()
            ? $vehicleId
            : null;
    }

    private function validTankId(mixed $tankId, array $context): ?int
    {
        $tankId = $this->positiveInteger($tankId);

        if (! $tankId) {
            return null;
        }

        return FuelTank::query()
            ->where('tenant_id', $context['tenant_id'])
            ->where('division_id', $context['division']->id)
            ->where('location_id', $context['location']->id)
            ->where('id', $tankId)
            ->exists()
            ? $tankId
            : null;
    }

    private function eventType(mixed $eventType): ?string
    {
        $eventType = is_string($eventType) ? trim($eventType) : null;

        return in_array($eventType, [
            FuelMovement::TYPE_RECEIPT,
            FuelMovement::TYPE_FILLING,
            FuelMovement::TYPE_ADJUSTMENT,
            FuelMovement::TYPE_REVERSAL,
        ], true)
            ? $eventType
            : null;
    }

    private function averageCostPerLiter(Collection $receipts, Collection $fillings): ?float
    {
        $liters = $this->sumDecimal($receipts, 'quantity_liters') + $this->sumDecimal($fillings, 'quantity_liters');
        $cost = $this->sumDecimal($receipts, 'total_cost') + $this->sumDecimal($fillings, 'total_cost');

        if ($liters <= 0 || $cost <= 0) {
            return null;
        }

        return round($cost / $liters, 4);
    }

    private function combinedConsumptionStatus(array $kmResult, array $hoursResult): string
    {
        if ($kmResult['status'] === 'calculado' || $hoursResult['status'] === 'calculado') {
            return 'calculado';
        }

        if ($kmResult['status'] === 'km_invalido') {
            return 'km_invalido';
        }

        if ($hoursResult['status'] === 'horas_invalidas') {
            return 'horas_invalidas';
        }

        return 'dados_insuficientes';
    }

    private function sumDecimal(Collection $items, string $field): float
    {
        return round($items->sum(fn ($item) => (float) ($item->{$field} ?? 0)), 3);
    }

    private function positiveInteger(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $value = (int) $value;

        return $value > 0 ? $value : null;
    }
}
