<?php

namespace App\Services\Reports;

use App\Models\MaintenanceRecord;
use App\Models\StockMovement;
use App\Models\FuelFilling;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class VehicleDossierReportService
{
    public function __construct(
        private readonly ReportContextService $reportContext
    ) {
    }

    public function build(array $filters = []): array
    {
        $context = $this->reportContext->resolve();

        if (! $context) {
            return $this->emptyResult(
                null,
                $this->emptyFilters($filters),
                ['Contexto ativo de divisao/unidade nao encontrado.']
            );
        }

        $appliedFilters = $this->filters($filters, $context);
        $validationErrors = $this->validationErrors($appliedFilters);
        $vehicle = null;

        if ($appliedFilters['vehicle_id']) {
            $vehicle = $this->vehicle($context, $appliedFilters['vehicle_id']);

            if (! $vehicle) {
                $validationErrors[] = 'Veiculo nao encontrado na tenant, divisao e unidade ativas.';
            }
        }

        if ($validationErrors !== []) {
            return $this->emptyResult($context, $appliedFilters, $validationErrors);
        }

        $maintenances = $this->maintenances($context, $vehicle, $appliedFilters);
        $stockConsumption = $this->stockConsumption($context, $vehicle, $maintenances);
        $fuelFillings = $this->fuelFillings($context, $vehicle, $appliedFilters);
        $fuelConsumption = $this->fuelConsumption($fuelFillings);
        $cancelledRecords = $this->cancelledRecords($context, $vehicle, $appliedFilters);

        return [
            'context' => $this->contextPayload($context),
            'applied_filters' => $appliedFilters,
            'validation' => [
                'is_valid' => true,
                'errors' => [],
            ],
            'vehicle' => $this->vehiclePayload($vehicle),
            'executive_summary' => $this->executiveSummary($maintenances, $stockConsumption, $fuelFillings, $fuelConsumption),
            'cost_policy' => $this->costPolicy(),
            'maintenances' => $maintenances,
            'stock_consumption' => $stockConsumption,
            'fuel_fillings' => $fuelFillings,
            'fuel_consumption' => $fuelConsumption,
            'tires_current' => collect(),
            'tire_events' => collect(),
            'operations' => collect(),
            'daily_checklists' => collect(),
            'km_hr_logs' => collect(),
            'alerts' => collect(),
            'cancelled_records' => $cancelledRecords,
            'audit_records' => collect(),
        ];
    }

    private function filters(array $filters, array $context): array
    {
        $startDate = $this->dateFromFilter($filters['start_date'] ?? null, true);
        $endDate = $this->dateFromFilter($filters['end_date'] ?? null, false);
        $periodIsValid = $startDate && $endDate ? $startDate->lte($endDate) : false;

        return [
            'vehicle_id' => $this->positiveInteger($filters['vehicle_id'] ?? null),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'period_is_valid' => $periodIsValid,
            'period_error' => $startDate && $endDate && ! $periodIsValid
                ? 'A data inicial nao pode ser maior que a data final.'
                : null,
            'include_cancelled' => $context['can_view_cancelled']
                && filter_var($filters['include_cancelled'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'include_audit' => $context['can_view_cancelled']
                && filter_var($filters['include_audit'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'include_drafts' => filter_var($filters['include_drafts'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'include_events_without_cost' => filter_var($filters['include_events_without_cost'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'include_fillings_without_km_hr' => filter_var($filters['include_fillings_without_km_hr'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'sections' => $this->sections($filters['sections'] ?? null),
        ];
    }

    private function emptyFilters(array $filters): array
    {
        return [
            'vehicle_id' => $this->positiveInteger($filters['vehicle_id'] ?? null),
            'start_date' => $this->dateFromFilter($filters['start_date'] ?? null, true),
            'end_date' => $this->dateFromFilter($filters['end_date'] ?? null, false),
            'period_is_valid' => false,
            'period_error' => null,
            'include_cancelled' => false,
            'include_audit' => false,
            'include_drafts' => filter_var($filters['include_drafts'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'include_events_without_cost' => filter_var($filters['include_events_without_cost'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'include_fillings_without_km_hr' => filter_var($filters['include_fillings_without_km_hr'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'sections' => $this->sections($filters['sections'] ?? null),
        ];
    }

    private function validationErrors(array $filters): array
    {
        $errors = [];

        if (! $filters['vehicle_id']) {
            $errors[] = 'Selecione um veiculo para gerar o dossie.';
        }

        if (! $filters['start_date']) {
            $errors[] = 'Informe a data inicial do periodo.';
        }

        if (! $filters['end_date']) {
            $errors[] = 'Informe a data final do periodo.';
        }

        if ($filters['period_error']) {
            $errors[] = $filters['period_error'];
        }

        return $errors;
    }

    private function vehicle(array $context, int $vehicleId): ?Vehicle
    {
        return $this->reportContext
            ->vehicleQuery($context)
            ->with(['division', 'location'])
            ->whereKey($vehicleId)
            ->first();
    }

    private function emptyResult(?array $context, array $filters, array $errors): array
    {
        return [
            'context' => $context ? $this->contextPayload($context) : null,
            'applied_filters' => $filters,
            'validation' => [
                'is_valid' => false,
                'errors' => $errors,
            ],
            'vehicle' => null,
            'executive_summary' => $this->emptyExecutiveSummary(),
            'cost_policy' => $this->costPolicy(),
            'maintenances' => collect(),
            'stock_consumption' => collect(),
            'fuel_fillings' => collect(),
            'fuel_consumption' => collect(),
            'tires_current' => collect(),
            'tire_events' => collect(),
            'operations' => collect(),
            'daily_checklists' => collect(),
            'km_hr_logs' => collect(),
            'alerts' => collect(),
            'cancelled_records' => collect(),
            'audit_records' => collect(),
        ];
    }

    private function contextPayload(array $context): array
    {
        return [
            'tenant_id' => $context['tenant_id'],
            'division' => $context['division'],
            'location' => $context['location'],
            'location_ids' => $context['location_ids'],
            'can_view_cancelled' => $context['can_view_cancelled'],
            'can_view_audit' => $context['can_view_cancelled'],
        ];
    }

    private function vehiclePayload(Vehicle $vehicle): array
    {
        return [
            'model' => $vehicle,
            'id' => $vehicle->id,
            'name' => $vehicle->name,
            'plate' => $vehicle->plate,
            'asset_code' => $vehicle->asset_code,
            'brand' => $vehicle->brand,
            'vehicle_model' => $vehicle->model,
            'year' => $vehicle->year,
            'type' => $vehicle->type,
            'status' => $vehicle->status,
            'operational_status' => $vehicle->operational_status,
            'current_km' => $vehicle->current_km,
            'current_hours' => $vehicle->current_hours,
            'last_km_update_at' => $vehicle->last_km_update_at,
            'last_hours_update_at' => $vehicle->last_hours_update_at,
            'division' => $vehicle->division,
            'location' => $vehicle->location,
            'notes' => $vehicle->notes,
        ];
    }

    private function emptyExecutiveSummary(): array
    {
        return [
            'maintenance_count' => 0,
            'maintenance_cost_registered' => 0.0,
            'maintenance_cost' => 0.0,
            'stock_consumed_cost' => 0.0,
            'stock_consumed_cost_estimated' => 0.0,
            'fuel_liters' => 0.0,
            'fuel_cost' => 0.0,
            'fuel_fillings_count' => 0,
            'fuel_by_product' => collect(),
            'fuel_consumption_by_product' => collect(),
            'fuel_fillings_without_km_hr' => 0,
            'fuel_invalid_readings_count' => 0,
            'installed_tires_count' => 0,
            'tire_measurements_count' => 0,
            'operations_count' => 0,
            'checklists_completed_count' => 0,
            'alerts_count' => 0,
            'operational_total_cost' => null,
            'cost_flags' => [
                'operational_total_is_final' => false,
                'contains_estimated_stock_cost' => false,
                'contains_uncalculated_fuel_consumption' => false,
            ],
            'notes' => [
                'Total operacional definitivo ainda nao calculado para evitar duplicidade entre custo da manutencao e pecas consumidas.',
            ],
        ];
    }

    private function executiveSummary(
        Collection $maintenances,
        Collection $stockConsumption,
        Collection $fuelFillings,
        Collection $fuelConsumption
    ): array
    {
        $registeredMaintenanceCost = round($maintenances->sum('total_cost'), 2);
        $stockConsumedCost = round($stockConsumption->sum('total_cost'), 2);
        $estimatedStockCost = round(
            $stockConsumption
                ->filter(fn (array $movement) => $movement['cost_is_estimated'])
                ->sum('total_cost'),
            2
        );
        $fuelByProduct = $fuelFillings
            ->groupBy('product_name')
            ->map(fn (Collection $items, string $productName) => [
                'product_name' => $productName,
                'fillings_count' => $items->count(),
                'liters' => round($items->sum('quantity_liters'), 3),
                'total_cost' => round($items->sum('total_cost'), 2),
            ])
            ->values();
        $fuelInvalidReadings = $fuelConsumption
            ->filter(fn (array $row) => in_array($row['status'], ['km_invalido', 'horas_invalidas'], true))
            ->count();
        $fuelHasUncalculatedConsumption = $fuelConsumption
            ->contains(fn (array $row) => $row['status'] !== 'calculado');

        return [
            ...$this->emptyExecutiveSummary(),
            'maintenance_count' => $maintenances->count(),
            'maintenance_cost_registered' => $registeredMaintenanceCost,
            'maintenance_cost' => $registeredMaintenanceCost,
            'stock_consumed_cost' => $stockConsumedCost,
            'stock_consumed_cost_estimated' => $estimatedStockCost,
            'fuel_liters' => round($fuelFillings->sum('quantity_liters'), 3),
            'fuel_cost' => round($fuelFillings->sum('total_cost'), 2),
            'fuel_fillings_count' => $fuelFillings->count(),
            'fuel_by_product' => $fuelByProduct,
            'fuel_consumption_by_product' => $fuelConsumption,
            'fuel_fillings_without_km_hr' => $fuelFillings
                ->filter(fn (array $filling) => $filling['vehicle_km'] === null && $filling['vehicle_hours'] === null)
                ->count(),
            'fuel_invalid_readings_count' => $fuelInvalidReadings,
            'alerts_count' => $fuelInvalidReadings,
            'cost_flags' => [
                'operational_total_is_final' => false,
                'contains_estimated_stock_cost' => $estimatedStockCost > 0,
                'contains_uncalculated_fuel_consumption' => $fuelHasUncalculatedConsumption,
            ],
        ];
    }

    private function costPolicy(): array
    {
        return [
            'maintenance_total_includes_stock' => 'unknown',
            'maintenance_cost_source' => 'maintenance_records.total_cost',
            'stock_cost_source' => 'stock_movements vinculados a maintenance_record_id',
            'operational_total_rule' => 'pending_definition',
            'warnings' => [
                'Nao foi encontrada regra tecnica conclusiva indicando se MaintenanceRecord.total_cost incorpora pecas do estoque.',
                'Por isso, o dossie mostra custo registrado da manutencao e pecas consumidas em linhas separadas, sem calcular total operacional definitivo.',
                'Custos de pneus nao serao tratados como custo operacional do periodo sem politica contabil explicita.',
                'Consumo km/l ou l/h nao sera calculado sem leituras confiaveis e crescentes.',
            ],
        ];
    }

    private function dateFromFilter(mixed $value, bool $startOfDay): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            $date = Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }

        return $startOfDay ? $date->startOfDay() : $date->endOfDay();
    }

    private function sections(mixed $sections): array
    {
        if (is_string($sections)) {
            $sections = array_filter(array_map('trim', explode(',', $sections)));
        }

        if (! is_array($sections)) {
            return [];
        }

        return collect($sections)
            ->filter(fn ($section) => is_string($section) && trim($section) !== '')
            ->map(fn ($section) => trim($section))
            ->unique()
            ->values()
            ->all();
    }

    private function positiveInteger(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $value = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $value ?: null;
    }

    private function maintenances(array $context, Vehicle $vehicle, array $filters): Collection
    {
        return $this->maintenanceBaseQuery($context, $vehicle, $filters, false)
            ->with(['procedure', 'values.field'])
            ->orderByDesc('performed_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (MaintenanceRecord $maintenance) => $this->maintenanceRow($maintenance))
            ->values();
    }

    private function cancelledRecords(array $context, Vehicle $vehicle, array $filters): Collection
    {
        if (! $filters['include_cancelled']) {
            return collect();
        }

        return $this->maintenanceBaseQuery($context, $vehicle, $filters, true)
            ->with(['procedure', 'canceller'])
            ->whereNotNull('cancelled_at')
            ->orderByDesc('cancelled_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (MaintenanceRecord $maintenance) => [
                ...$this->maintenanceRow($maintenance),
                'module' => 'maintenance',
                'record_type' => 'Manutencao cancelada',
                'record_label' => $maintenance->procedure?->name ?? 'Manutencao rapida',
                'cancelled_at' => $maintenance->cancelled_at,
                'cancel_reason' => $maintenance->cancel_reason,
                'cancelled_by' => $maintenance->canceller?->name,
                'considered_in_operational_indicators' => false,
            ])
            ->merge($this->cancelledFuelFillings($context, $vehicle, $filters))
            ->sortByDesc('cancelled_at')
            ->values();
    }

    private function maintenanceBaseQuery(
        array $context,
        Vehicle $vehicle,
        array $filters,
        bool $includeCancelled
    ): Builder {
        return $this->reportContext
            ->maintenanceQuery($context, $includeCancelled)
            ->where('vehicle_id', $vehicle->id)
            ->whereBetween('performed_at', [$filters['start_date'], $filters['end_date']]);
    }

    private function maintenanceRow(MaintenanceRecord $maintenance): array
    {
        return [
            'model' => $maintenance,
            'id' => $maintenance->id,
            'date' => $maintenance->performed_at,
            'procedure' => $maintenance->procedure,
            'procedure_name' => $maintenance->procedure?->name ?? 'Manutencao rapida',
            'maintenance_type' => $maintenance->maintenance_type,
            'provider_name' => $maintenance->provider_name,
            'performed_km' => $maintenance->performed_km,
            'performed_hours' => $maintenance->performed_hours,
            'total_cost' => (float) ($maintenance->total_cost ?? 0),
            'extra_cost' => (float) ($maintenance->extra_cost ?? 0),
            'reason' => $maintenance->reason,
            'notes' => $maintenance->notes,
            'responsible' => null,
            'dynamic_values' => $this->dynamicValues($maintenance),
            'is_cancelled' => $maintenance->cancelled_at !== null,
        ];
    }

    private function dynamicValues(MaintenanceRecord $maintenance): Collection
    {
        return $maintenance->values
            ->filter(fn ($value) => $value->field !== null && $value->value !== null && $value->value !== '')
            ->sortBy(fn ($value) => $value->field?->sort_order ?? $value->id)
            ->take(6)
            ->map(fn ($value) => [
                'label' => $value->field?->label ?? 'Campo',
                'value' => $value->value,
                'quantity' => $value->quantity,
                'field_type' => $value->field?->field_type,
            ])
            ->values();
    }

    private function stockConsumption(array $context, Vehicle $vehicle, Collection $maintenances): Collection
    {
        $maintenanceIds = $maintenances->pluck('id')->filter()->values();

        if ($maintenanceIds->isEmpty()) {
            return collect();
        }

        return $this->reportContext
            ->stockMovementQuery($context)
            ->with(['stockItem.category', 'maintenanceRecord.procedure', 'maintenanceRecord.vehicle'])
            ->whereIn('maintenance_record_id', $maintenanceIds)
            ->where('movement_type', 'out')
            ->whereNull('cancelled_at')
            ->whereNull('reversed_from_movement_id')
            ->whereHas('maintenanceRecord', function (Builder $query) use ($vehicle) {
                $query
                    ->where('vehicle_id', $vehicle->id)
                    ->whereNull('cancelled_at');
            })
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (StockMovement $movement) => $this->stockConsumptionRow($movement))
            ->values();
    }

    private function stockConsumptionRow(StockMovement $movement): array
    {
        $item = $movement->stockItem;
        $unitCost = $movement->unit_cost !== null
            ? (float) $movement->unit_cost
            : (float) ($item?->unit_cost ?? 0);
        $usesFallbackCost = $movement->unit_cost === null && $item?->unit_cost !== null;

        return [
            'model' => $movement,
            'id' => $movement->id,
            'date' => $movement->created_at,
            'maintenance_id' => $movement->maintenance_record_id,
            'maintenance' => $movement->maintenanceRecord,
            'procedure' => $movement->maintenanceRecord?->procedure,
            'procedure_name' => $movement->maintenanceRecord?->procedure?->name ?? 'Manutencao rapida',
            'item' => $item,
            'item_name' => $item?->name ?? '-',
            'category_name' => $item?->category?->name ?? '-',
            'quantity' => (float) $movement->quantity,
            'unit_cost' => $unitCost,
            'total_cost' => round((float) $movement->quantity * $unitCost, 2),
            'cost_is_estimated' => $usesFallbackCost,
            'cost_note' => $usesFallbackCost
                ? 'Custo estimado pelo custo unitario atual do item.'
                : null,
            'description' => $movement->description,
            'responsible' => null,
        ];
    }

    private function fuelFillings(array $context, Vehicle $vehicle, array $filters): Collection
    {
        return FuelFilling::query()
            ->with(['tank.product', 'product', 'driver', 'responsible'])
            ->where('tenant_id', $context['tenant_id'])
            ->where('division_id', $context['division']->id)
            ->where('location_id', $context['location']->id)
            ->where('vehicle_id', $vehicle->id)
            ->whereNull('cancelled_at')
            ->whereBetween('filled_at', [$filters['start_date'], $filters['end_date']])
            ->orderByDesc('filled_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (FuelFilling $filling) => $this->fuelFillingRow($filling))
            ->values();
    }

    private function fuelFillingRow(FuelFilling $filling): array
    {
        return [
            'model' => $filling,
            'id' => $filling->id,
            'date' => $filling->filled_at,
            'product' => $filling->product,
            'product_name' => $filling->product?->name ?? $filling->tank?->product?->name ?? '-',
            'tank' => $filling->tank,
            'tank_name' => $filling->tank?->name ?? '-',
            'driver' => $filling->driver,
            'driver_name' => $filling->driver?->name ?? '-',
            'vehicle_km' => $filling->vehicle_km !== null ? (float) $filling->vehicle_km : null,
            'vehicle_hours' => $filling->vehicle_hours !== null ? (float) $filling->vehicle_hours : null,
            'quantity_liters' => (float) $filling->quantity_liters,
            'unit_cost' => $filling->unit_cost !== null ? (float) $filling->unit_cost : null,
            'total_cost' => $filling->total_cost !== null ? (float) $filling->total_cost : 0.0,
            'responsible' => $filling->responsible,
            'responsible_name' => $filling->responsible?->name ?? '-',
            'notes' => $filling->notes,
            'is_cancelled' => $filling->cancelled_at !== null,
        ];
    }

    private function fuelConsumption(Collection $fuelFillings): Collection
    {
        return $fuelFillings
            ->groupBy('product_name')
            ->map(function (Collection $items, string $productName) {
                $ordered = $items
                    ->sortBy([
                        ['date', 'asc'],
                        ['id', 'asc'],
                    ])
                    ->values();
                $km = $this->counterConsumption($ordered, 'vehicle_km');
                $hours = $this->counterConsumption($ordered, 'vehicle_hours');

                return [
                    'product_name' => $productName,
                    'fillings_count' => $ordered->count(),
                    'total_liters' => round($ordered->sum('quantity_liters'), 3),
                    'total_cost' => round($ordered->sum('total_cost'), 2),
                    'km_consumption' => $km,
                    'hours_consumption' => $hours,
                    'status' => $this->combinedFuelConsumptionStatus($ordered, $km, $hours),
                ];
            })
            ->values();
    }

    private function counterConsumption(Collection $items, string $counterField): array
    {
        $valid = $items
            ->filter(fn (array $filling) => $filling[$counterField] !== null)
            ->values();

        if ($valid->count() < 2) {
            return [
                'status' => $items->every(fn (array $filling) => $filling['vehicle_km'] === null && $filling['vehicle_hours'] === null)
                    ? 'sem_km_hr'
                    : 'dados_insuficientes',
                'initial' => null,
                'final' => null,
                'delta' => null,
                'liters' => round($items->sum('quantity_liters'), 3),
                'value' => null,
            ];
        }

        $previous = null;

        foreach ($valid as $filling) {
            $current = (float) $filling[$counterField];

            if ($previous !== null && $current <= $previous) {
                return [
                    'status' => $counterField === 'vehicle_km' ? 'km_invalido' : 'horas_invalidas',
                    'initial' => (float) $valid->first()[$counterField],
                    'final' => (float) $valid->last()[$counterField],
                    'delta' => null,
                    'liters' => round($items->sum('quantity_liters'), 3),
                    'value' => null,
                ];
            }

            $previous = $current;
        }

        $initial = (float) $valid->first()[$counterField];
        $final = (float) $valid->last()[$counterField];
        $delta = $final - $initial;

        if ($delta <= 0) {
            return [
                'status' => $counterField === 'vehicle_km' ? 'km_invalido' : 'horas_invalidas',
                'initial' => $initial,
                'final' => $final,
                'delta' => null,
                'liters' => round($items->sum('quantity_liters'), 3),
                'value' => null,
            ];
        }

        $firstDate = $valid->first()['date'];
        $lastDate = $valid->last()['date'];
        $intervalLiters = $items
            ->filter(fn (array $filling) => $filling['date']->gt($firstDate) && $filling['date']->lte($lastDate))
            ->sum('quantity_liters');

        if ($intervalLiters <= 0) {
            $intervalLiters = $items->sum('quantity_liters');
        }

        return [
            'status' => 'calculado',
            'initial' => $initial,
            'final' => $final,
            'delta' => $delta,
            'liters' => round($intervalLiters, 3),
            'value' => $counterField === 'vehicle_km'
                ? round($delta / max($intervalLiters, 0.001), 3)
                : round($intervalLiters / $delta, 3),
        ];
    }

    private function combinedFuelConsumptionStatus(Collection $items, array $km, array $hours): string
    {
        if ($km['status'] === 'calculado' || $hours['status'] === 'calculado') {
            return 'calculado';
        }

        if ($items->every(fn (array $filling) => $filling['vehicle_km'] === null && $filling['vehicle_hours'] === null)) {
            return 'sem_km_hr';
        }

        if ($km['status'] === 'km_invalido') {
            return 'km_invalido';
        }

        if ($hours['status'] === 'horas_invalidas') {
            return 'horas_invalidas';
        }

        return 'dados_insuficientes';
    }

    private function cancelledFuelFillings(array $context, Vehicle $vehicle, array $filters): Collection
    {
        return FuelFilling::query()
            ->with(['tank.product', 'product', 'canceller'])
            ->where('tenant_id', $context['tenant_id'])
            ->where('division_id', $context['division']->id)
            ->where('location_id', $context['location']->id)
            ->where('vehicle_id', $vehicle->id)
            ->whereNotNull('cancelled_at')
            ->whereBetween('cancelled_at', [$filters['start_date'], $filters['end_date']])
            ->get()
            ->map(fn (FuelFilling $filling) => [
                'module' => 'fuel',
                'record_type' => 'Abastecimento cancelado',
                'record_label' => ($filling->product?->name ?? $filling->tank?->product?->name ?? 'Produto') . ' - ' . ($filling->tank?->name ?? 'Tanque'),
                'date' => $filling->filled_at,
                'total_cost' => (float) ($filling->total_cost ?? 0),
                'quantity_liters' => (float) $filling->quantity_liters,
                'cancelled_at' => $filling->cancelled_at,
                'cancel_reason' => $filling->cancel_reason,
                'cancelled_by' => $filling->canceller?->name,
                'considered_in_operational_indicators' => false,
            ])
            ->values();
    }
}
