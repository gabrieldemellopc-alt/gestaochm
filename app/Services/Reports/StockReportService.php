<?php

namespace App\Services\Reports;

use App\Models\Procedure;
use App\Models\StockCategory;
use App\Models\StockItem;
use App\Models\StockMovement;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class StockReportService
{
    private const STALE_MOVEMENT_DAYS = 90;

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
        $items = $this->inventory($context, $filters);
        $movements = $this->movementsPeriod($context, $filters);
        $manualEntries = $this->manualEntries($movements);
        $manualOutputs = $this->manualOutputs($movements);
        $maintenanceConsumption = $this->maintenanceConsumption($movements);
        $reversalMovements = $this->reversalMovements($movements);
        $cancelledRecords = $this->cancelledRecords($context, $filters);

        return [
            'context' => $context,
            'applied_filters' => $filters,
            'items' => $items,
            'categories' => $this->categories($context),
            'vehicles' => $this->vehicles($context),
            'procedures' => $this->procedures($context),
            'inventory_summary' => $this->inventorySummary($items),
            'low_stock_items' => $items
                ->filter(fn (array $item) => $item['status'] === 'low')
                ->values(),
            'zero_stock_items' => $items
                ->filter(fn (array $item) => $item['status'] === 'zero')
                ->values(),
            'stale_items' => $items
                ->filter(fn (array $item) => $item['is_stale'])
                ->values(),
            'movements_period' => $movements,
            'manual_entries' => $manualEntries,
            'manual_outputs' => $manualOutputs,
            'maintenance_consumption' => $maintenanceConsumption,
            'reversal_movements' => $reversalMovements,
            'cancelled_records' => $cancelledRecords,
            'top_consumed_items' => $this->topConsumedItems($maintenanceConsumption),
            'estimated_inventory_value' => round($items->sum('estimated_value'), 2),
            'estimated_inventory_value_note' => 'Valor estimado pelo saldo atual multiplicado pelo custo unitario atual do item; nao representa custo contabil fechado.',
            'total_entries_quantity' => $this->sumQuantity($manualEntries),
            'total_outputs_quantity' => $this->sumQuantity($manualOutputs),
            'total_consumed_cost' => round($maintenanceConsumption->sum('total_cost'), 2),
            'latest_movements' => $this->latestMovements($context, $filters),
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
        $categoryId = $this->validCategoryId(
            $filters['stock_category_id'] ?? $filters['category_id'] ?? $filters['category'] ?? null,
            $context
        );

        $movementType = $filters['movement_type'] ?? null;
        $movementType = is_string($movementType) ? trim($movementType) : null;

        if (! in_array($movementType, ['in', 'out', 'maintenance', 'reversal', 'cancelled'], true)) {
            $movementType = null;
        }

        return [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'period_is_valid' => $periodIsValid,
            'period_error' => $periodIsValid
                ? null
                : 'A data inicial nao pode ser maior que a data final.',
            'stock_item_id' => $this->validItemId($filters['stock_item_id'] ?? null, $context),
            'stock_category_id' => $categoryId,
            'movement_type' => $movementType,
            'vehicle_id' => $this->validVehicleId($filters['vehicle_id'] ?? null, $context),
            'procedure_id' => $this->validProcedureId($filters['procedure_id'] ?? null, $context),
            'only_low_stock' => filter_var($filters['only_low_stock'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'only_zero_stock' => filter_var($filters['only_zero_stock'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'only_stale' => filter_var($filters['only_stale'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'only_maintenance_consumption' => filter_var($filters['only_maintenance_consumption'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'include_cancelled' => $context['can_view_cancelled']
                && filter_var($filters['include_cancelled'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'stale_days' => max(1, (int) ($filters['stale_days'] ?? self::STALE_MOVEMENT_DAYS)),
        ];
    }

    private function inventory(array $context, array $filters): Collection
    {
        $query = StockItem::query()
            ->with(['category'])
            ->where('tenant_id', $context['tenant_id'])
            ->where('location_id', $context['location']->id)
            ->orderBy('name');

        $this->applyItemFilters($query, $filters);

        return $query
            ->get()
            ->map(function (StockItem $item) use ($context, $filters) {
                $lastMovement = $this->lastMovementForItem($item, $context);
                $daysWithoutMovement = $lastMovement
                    ? (int) Carbon::parse($lastMovement->created_at)->diffInDays(now())
                    : null;
                $quantity = (float) $item->quantity;
                $minimum = (float) $item->minimum_quantity;
                $unitCost = (float) ($item->unit_cost ?? 0);

                return [
                    'item' => $item,
                    'category' => $item->category,
                    'unit' => $item->unit,
                    'current_quantity' => $quantity,
                    'minimum_quantity' => $minimum,
                    'status' => $this->stockStatus($quantity, $minimum),
                    'last_movement' => $lastMovement,
                    'days_without_movement' => $daysWithoutMovement,
                    'is_stale' => $daysWithoutMovement === null || $daysWithoutMovement >= $filters['stale_days'],
                    'estimated_value' => round($quantity * $unitCost, 2),
                    'estimated_value_is_accounting_cost' => false,
                    'estimated_value_note' => 'Estimativa baseada no custo unitario atual do item.',
                ];
            })
            ->filter(function (array $item) use ($filters) {
                if ($filters['only_low_stock'] && $item['status'] !== 'low') {
                    return false;
                }

                if ($filters['only_zero_stock'] && $item['status'] !== 'zero') {
                    return false;
                }

                if ($filters['only_stale'] && ! $item['is_stale']) {
                    return false;
                }

                return true;
            })
            ->values();
    }

    private function movementsPeriod(array $context, array $filters): Collection
    {
        if (! $filters['period_is_valid']) {
            return collect();
        }

        if ($filters['movement_type'] === 'cancelled') {
            return collect();
        }

        $query = $this->baseMovementQuery($context)
            ->whereBetween('created_at', [$filters['start_date'], $filters['end_date']])
            ->whereNull('cancelled_at')
            ->orderByDesc('created_at');

        $this->applyMovementFilters($query, $filters);

        return $query
            ->get()
            ->map(fn (StockMovement $movement) => $this->movementRow($movement));
    }

    private function manualEntries(Collection $movements): Collection
    {
        return $movements
            ->filter(fn (array $movement) => $movement['classification'] === 'manual_entry')
            ->values();
    }

    private function manualOutputs(Collection $movements): Collection
    {
        return $movements
            ->filter(fn (array $movement) => $movement['classification'] === 'manual_output')
            ->values();
    }

    private function maintenanceConsumption(Collection $movements): Collection
    {
        return $movements
            ->filter(fn (array $movement) => $movement['classification'] === 'maintenance_consumption')
            ->values();
    }

    private function reversalMovements(Collection $movements): Collection
    {
        return $movements
            ->filter(fn (array $movement) => $movement['classification'] === 'reversal')
            ->values();
    }

    private function cancelledRecords(array $context, array $filters): Collection
    {
        if (! $filters['include_cancelled'] || ! $filters['period_is_valid']) {
            return collect();
        }

        $query = $this->baseMovementQuery($context)
            ->whereNotNull('cancelled_at')
            ->whereBetween('cancelled_at', [$filters['start_date'], $filters['end_date']])
            ->orderByDesc('cancelled_at');

        $this->applyMovementFilters($query, $filters);

        return $query
            ->get()
            ->map(function (StockMovement $movement) {
                $row = $this->movementRow($movement);
                $row['considered_in_operational_indicators'] = false;
                $row['cancel_reason'] = $movement->cancel_reason;
                $row['cancelled_by'] = $movement->canceller?->name;
                $row['cancelled_at'] = $movement->cancelled_at;

                return $row;
            })
            ->values();
    }

    private function latestMovements(array $context, array $filters): Collection
    {
        $query = $this->baseMovementQuery($context)
            ->whereNull('cancelled_at')
            ->orderByDesc('created_at')
            ->limit(10);

        $this->applyMovementFilters($query, $filters);

        return $query
            ->get()
            ->map(fn (StockMovement $movement) => $this->movementRow($movement));
    }

    private function movementRow(StockMovement $movement): array
    {
        $item = $movement->stockItem;
        $maintenance = $movement->maintenanceRecord;
        $procedure = $this->movementProcedure($movement);
        $unitCost = $movement->unit_cost !== null
            ? (float) $movement->unit_cost
            : (float) ($item?->unit_cost ?? 0);
        $usesFallbackCost = $movement->unit_cost === null && $item?->unit_cost !== null;

        return [
            'movement' => $movement,
            'id' => $movement->id,
            'date' => $movement->created_at,
            'stock_item' => $item,
            'item_name' => $item?->name,
            'category' => $item?->category,
            'category_name' => $item?->category?->name,
            'movement_type' => $movement->movement_type,
            'classification' => $this->movementClassification($movement),
            'classification_label' => $this->movementClassificationLabel($movement),
            'quantity' => (float) $movement->quantity,
            'unit_cost' => $unitCost,
            'total_cost' => round((float) $movement->quantity * $unitCost, 2),
            'cost_is_estimated' => $usesFallbackCost,
            'cost_note' => $usesFallbackCost
                ? 'Custo estimado pelo custo unitario atual do item.'
                : null,
            'description' => $movement->description,
            'responsible' => null,
            'maintenance' => $maintenance,
            'maintenance_id' => $movement->maintenance_record_id,
            'maintenance_record_item_id' => $movement->maintenance_record_item_id,
            'vehicle' => $maintenance?->vehicle,
            'procedure' => $procedure['procedure'],
            'procedure_name' => $procedure['name'],
            'procedure_source' => $procedure['source'],
            'is_cancelled' => $movement->cancelled_at !== null,
            'is_reversal' => $movement->reversed_from_movement_id !== null,
            'reversed_from_movement_id' => $movement->reversed_from_movement_id,
            'reversal_movement_id' => $movement->reversal_movement_id,
            'considered_in_operational_indicators' => $this->isOperationalMovement($movement),
        ];
    }

    private function movementClassification(StockMovement $movement): string
    {
        if ($movement->cancelled_at !== null) {
            return 'cancelled';
        }

        if ($this->isReversalMovement($movement)) {
            return 'reversal';
        }

        if (
            $movement->maintenance_record_id !== null
            && $movement->movement_type === 'out'
            && $movement->maintenanceRecord?->cancelled_at === null
        ) {
            return 'maintenance_consumption';
        }

        if ($movement->movement_type === 'in' && $movement->maintenance_record_id === null) {
            return 'manual_entry';
        }

        if ($movement->movement_type === 'out' && $movement->maintenance_record_id === null) {
            return 'manual_output';
        }

        return 'other';
    }

    private function movementClassificationLabel(StockMovement $movement): string
    {
        return match ($this->movementClassification($movement)) {
            'manual_entry' => 'Entrada manual',
            'manual_output' => 'Saida manual',
            'maintenance_consumption' => 'Consumo por manutencao',
            'reversal' => 'Reversao',
            'cancelled' => 'Cancelado',
            default => 'Outro',
        };
    }

    private function isOperationalMovement(StockMovement $movement): bool
    {
        return $movement->cancelled_at === null
            && ! $this->isReversalMovement($movement)
            && (
                $movement->maintenance_record_id === null
                || $movement->maintenanceRecord?->cancelled_at === null
            );
    }

    private function inventorySummary(Collection $items): array
    {
        return [
            'total_items' => $items->count(),
            'active_items' => $items->filter(fn (array $item) => (bool) $item['item']->active)->count(),
            'low_stock' => $items->where('status', 'low')->count(),
            'zero_stock' => $items->where('status', 'zero')->count(),
            'stale_items' => $items->filter(fn (array $item) => $item['is_stale'])->count(),
            'estimated_value' => round($items->sum('estimated_value'), 2),
        ];
    }

    private function topConsumedItems(Collection $maintenanceConsumption): Collection
    {
        return $maintenanceConsumption
            ->groupBy('stock_item.id')
            ->map(function (Collection $items) {
                $first = $items->first();

                return [
                    'stock_item' => $first['stock_item'],
                    'item_name' => $first['item_name'],
                    'category_name' => $first['category_name'],
                    'quantity' => $items->sum('quantity'),
                    'total_cost' => round($items->sum('total_cost'), 2),
                    'cost_has_estimates' => $items->contains(fn (array $item) => $item['cost_is_estimated']),
                ];
            })
            ->sortByDesc('quantity')
            ->values();
    }

    private function categories(array $context): Collection
    {
        return StockCategory::query()
            ->where('tenant_id', $context['tenant_id'])
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function vehicles(array $context): Collection
    {
        return $this->reportContext
            ->vehicleQuery($context)
            ->orderBy('name')
            ->get(['id', 'name', 'plate']);
    }

    private function procedures(array $context): Collection
    {
        return Procedure::query()
            ->where('tenant_id', $context['tenant_id'])
            ->where('location_id', $context['location']->id)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function baseMovementQuery(array $context): Builder
    {
        return $this->reportContext
            ->stockMovementQuery($context)
            ->with([
                'stockItem.category',
                'maintenanceRecord.vehicle',
                'maintenanceRecord.procedure',
                'maintenanceRecordItem.procedure',
                'canceller',
                'reversalMovement',
                'reversedFromMovement',
            ]);
    }

    private function applyItemFilters(Builder $query, array $filters): void
    {
        if ($filters['stock_item_id']) {
            $query->whereKey($filters['stock_item_id']);
        }

        if ($filters['stock_category_id']) {
            $query->where('stock_category_id', $filters['stock_category_id']);
        }
    }

    private function applyMovementFilters(Builder $query, array $filters): void
    {
        if ($filters['stock_item_id']) {
            $query->where('stock_item_id', $filters['stock_item_id']);
        }

        if ($filters['stock_category_id']) {
            $query->whereHas('stockItem', function (Builder $query) use ($filters) {
                $query->where('stock_category_id', $filters['stock_category_id']);
            });
        }

        if ($filters['vehicle_id']) {
            $query->whereHas('maintenanceRecord.vehicle', function (Builder $query) use ($filters) {
                $query->whereKey($filters['vehicle_id']);
            });
        }

        if ($filters['procedure_id']) {
            $query->where(function (Builder $query) use ($filters) {
                $query
                    ->whereHas('maintenanceRecordItem', function (Builder $query) use ($filters) {
                        $query->where('procedure_id', $filters['procedure_id']);
                    })
                    ->orWhere(function (Builder $query) use ($filters) {
                        $query
                            ->whereNull('maintenance_record_item_id')
                            ->whereHas('maintenanceRecord', function (Builder $query) use ($filters) {
                                $query->where('procedure_id', $filters['procedure_id']);
                            });
                    });
            });
        }

        if ($filters['only_maintenance_consumption'] || $filters['movement_type'] === 'maintenance') {
            $query
                ->whereNotNull('maintenance_record_id')
                ->where('movement_type', 'out')
                ->whereHas('maintenanceRecord', fn (Builder $query) => $query->whereNull('cancelled_at'))
                ->whereNull('cancelled_at')
                ->whereNull('reversed_from_movement_id');

            return;
        }

        match ($filters['movement_type']) {
            'in' => $query
                ->where('movement_type', 'in')
                ->whereNull('maintenance_record_id')
                ->whereNull('reversed_from_movement_id'),
            'out' => $query
                ->where('movement_type', 'out')
                ->whereNull('maintenance_record_id')
                ->whereNull('reversed_from_movement_id'),
            'reversal' => $query->where(function (Builder $query) {
                $query
                    ->whereNotNull('reversed_from_movement_id')
                    ->orWhere('description', 'like', 'Reversao%');
            }),
            'cancelled' => $query->whereNotNull('cancelled_at'),
            default => null,
        };
    }

    private function lastMovementForItem(StockItem $item, array $context): ?StockMovement
    {
        return StockMovement::query()
            ->where('tenant_id', $context['tenant_id'])
            ->where('location_id', $context['location']->id)
            ->where('stock_item_id', $item->id)
            ->whereNull('cancelled_at')
            ->latest('created_at')
            ->first();
    }

    private function stockStatus(float $quantity, float $minimum): string
    {
        if ($quantity <= 0) {
            return 'zero';
        }

        if ($minimum > 0 && $quantity <= $minimum) {
            return 'low';
        }

        return 'normal';
    }

    private function isReversalMovement(StockMovement $movement): bool
    {
        return $movement->reversed_from_movement_id !== null
            || str_starts_with((string) $movement->description, 'Reversao');
    }

    private function movementProcedure(StockMovement $movement): array
    {
        if ($movement->maintenanceRecordItem?->procedure) {
            return [
                'procedure' => $movement->maintenanceRecordItem->procedure,
                'name' => $movement->maintenanceRecordItem->procedure->name,
                'source' => 'item',
            ];
        }

        if ($movement->maintenanceRecord?->procedure) {
            return [
                'procedure' => $movement->maintenanceRecord->procedure,
                'name' => $movement->maintenanceRecord->procedure->name,
                'source' => 'legado',
            ];
        }

        if ($movement->maintenance_record_id !== null) {
            return [
                'procedure' => null,
                'name' => 'Item de manutencao',
                'source' => 'maintenance',
            ];
        }

        return [
            'procedure' => null,
            'name' => null,
            'source' => null,
        ];
    }

    private function sumQuantity(Collection $items): float
    {
        return round($items->sum('quantity'), 3);
    }

    private function validItemId(mixed $itemId, array $context): ?int
    {
        $itemId = $this->positiveInteger($itemId);

        if (! $itemId) {
            return null;
        }

        return StockItem::query()
            ->where('tenant_id', $context['tenant_id'])
            ->where('location_id', $context['location']->id)
            ->whereKey($itemId)
            ->exists()
            ? $itemId
            : null;
    }

    private function validCategoryId(mixed $category, array $context): ?int
    {
        if (is_numeric($category)) {
            $categoryId = $this->positiveInteger($category);

            return $categoryId
                && StockCategory::query()
                    ->where('tenant_id', $context['tenant_id'])
                    ->whereKey($categoryId)
                    ->exists()
                ? $categoryId
                : null;
        }

        $category = is_string($category) ? trim($category) : null;

        if (! $category) {
            return null;
        }

        return StockCategory::query()
            ->where('tenant_id', $context['tenant_id'])
            ->where('name', $category)
            ->value('id');
    }

    private function validVehicleId(mixed $vehicleId, array $context): ?int
    {
        $vehicleId = $this->positiveInteger($vehicleId);

        if (! $vehicleId) {
            return null;
        }

        return $this->reportContext
            ->vehicleQuery($context)
            ->whereKey($vehicleId)
            ->exists()
            ? $vehicleId
            : null;
    }

    private function validProcedureId(mixed $procedureId, array $context): ?int
    {
        $procedureId = $this->positiveInteger($procedureId);

        if (! $procedureId) {
            return null;
        }

        return Procedure::query()
            ->where('tenant_id', $context['tenant_id'])
            ->where('location_id', $context['location']->id)
            ->whereKey($procedureId)
            ->exists()
            ? $procedureId
            : null;
    }

    private function positiveInteger(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $value = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $value ?: null;
    }
}
