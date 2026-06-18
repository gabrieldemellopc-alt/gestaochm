<?php

namespace App\Services;

use App\Models\MaintenanceRecord;
use App\Models\MaintenanceRecordValue;
use App\Models\Procedure;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\AuditLogService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MaintenanceService
{
    public static function cancel(
        MaintenanceRecord $maintenance,
        string $reason,
        User $user
    ): MaintenanceRecord {
        return DB::transaction(function () use ($maintenance, $reason, $user) {
            $maintenance = MaintenanceRecord::query()
                ->with(['vehicle', 'procedure', 'values'])
                ->whereKey($maintenance->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($maintenance->cancelled_at) {
                throw ValidationException::withMessages([
                    'maintenance' => 'Esta manutencao ja foi cancelada.',
                ]);
            }

            $vehicle = $maintenance->vehicle;

            if (! $vehicle) {
                throw ValidationException::withMessages([
                    'maintenance' => 'Nao foi possivel localizar o veiculo da manutencao.',
                ]);
            }

            $before = $maintenance->toArray();
            $reverseMovements = collect();
            $stockMovements = self::stockMovementsForMaintenance($maintenance);

            foreach ($stockMovements as $movement) {
                $stockItem = StockItem::query()
                    ->where('tenant_id', $movement->tenant_id)
                    ->where('location_id', $movement->location_id)
                    ->whereKey($movement->stock_item_id)
                    ->lockForUpdate()
                    ->first();

                if (! $stockItem) {
                    throw ValidationException::withMessages([
                        'maintenance' => 'Nao foi possivel reverter o estoque consumido pela manutencao.',
                    ]);
                }

                $reverseMovement = StockMovement::create([
                    'tenant_id' => $movement->tenant_id,
                    'location_id' => $movement->location_id,
                    'stock_item_id' => $movement->stock_item_id,
                    'maintenance_record_id' => $maintenance->id,
                    'movement_type' => 'in',
                    'quantity' => $movement->quantity,
                    'unit_cost' => $movement->unit_cost,
                    'description' => 'Reversao do cancelamento da manutencao #'.$maintenance->id,
                ]);

                $stockItem->quantity = (float) $stockItem->quantity + (float) $movement->quantity;
                $stockItem->save();

                $reverseMovements->push($reverseMovement);

                app(AuditLogService::class)->created($reverseMovement, [
                    'tenant_id' => $movement->tenant_id,
                    'division_id' => $vehicle->division_id,
                    'location_id' => $movement->location_id,
                    'module' => 'stock',
                    'summary' => 'Movimento reverso criado pelo cancelamento da manutencao #'.$maintenance->id.'.',
                    'after_data' => $reverseMovement->toArray(),
                    'metadata' => [
                        'maintenance_record_id' => $maintenance->id,
                        'original_stock_movement_id' => $movement->id,
                        'stock_item_id' => $movement->stock_item_id,
                        'quantity_reversed' => $movement->quantity,
                    ],
                    'reason' => $reason,
                ]);
            }

            $maintenance->update([
                'cancelled_at' => now(),
                'cancelled_by' => $user->id,
                'cancel_reason' => $reason,
            ]);

            $maintenanceAfter = $maintenance->fresh(['vehicle', 'procedure', 'values']);

            app(AuditLogService::class)->cancelled($maintenanceAfter, [
                'tenant_id' => $vehicle->tenant_id,
                'division_id' => $vehicle->division_id,
                'location_id' => $vehicle->location_id,
                'module' => 'maintenance',
                'summary' => 'Manutencao cancelada para o veiculo ' . $vehicle->plate . '.',
                'before_data' => $before,
                'after_data' => [
                    'cancelled_at' => $maintenanceAfter->cancelled_at,
                    'cancelled_by' => $maintenanceAfter->cancelled_by,
                    'cancel_reason' => $maintenanceAfter->cancel_reason,
                ],
                'metadata' => [
                    'vehicle_id' => $vehicle->id,
                    'procedure_id' => $maintenanceAfter->procedure_id,
                    'maintenance_type' => $maintenanceAfter->maintenance_type,
                    'reverse_stock_movements' => $reverseMovements
                        ->map(fn ($movement) => $movement->toArray())
                        ->values()
                        ->all(),
                ],
                'reason' => $reason,
            ]);

            return $maintenanceAfter;
        });
    }

    public static function create(array $data, Vehicle $vehicle): array
    {
        return DB::transaction(function () use ($data, $vehicle) {
            $procedure = Procedure::with('fields')
                ->where('tenant_id', $vehicle->tenant_id)
                ->where('location_id', $vehicle->location_id)
                ->findOrFail($data['procedure_id']);

            $executionType = $data['maintenance_type'] ?? 'external';
            $stockUsage = collect();
            $stockItems = collect();

            if ($executionType === 'internal') {
                $stockUsage = self::collectStockUsage($procedure->fields, $data);
                $stockItems = self::lockAndValidateStockItems($stockUsage, $vehicle);
            }

            [$nextDueKm, $nextDueHours, $nextDueDate] =
                self::calculateNextDue($procedure, $data);

            $maintenance = MaintenanceRecord::create([
                'tenant_id' => $vehicle->tenant_id,
                'vehicle_id' => $vehicle->id,
                'procedure_id' => $procedure->id,
                'maintenance_type' => $executionType,
                'performed_km' => $data['performed_km'] ?? null,
                'performed_hours' => $data['performed_hours'] ?? null,
                'performed_at' => $data['performed_at'],
                'next_due_km' => $nextDueKm,
                'next_due_hours' => $nextDueHours,
                'next_due_date' => $nextDueDate,
                'extra_cost' => $data['extra_cost'] ?? 0,
                'reason' => $data['reason'] ?? null,
                'provider_name' => $executionType === 'external'
                    ? ($data['provider_name'] ?? null)
                    : null,
                'notes' => $data['notes'] ?? null,
            ]);

            $totalCost = 0;
            $updatedStockItem = null;
            $fieldValues = $data['fields'] ?? [];

            foreach ($procedure->fields as $field) {
                $value = $fieldValues[$field->slug] ?? null;
                $quantity = $field->field_type === 'stock_item'
                    ? ($fieldValues[$field->slug.'_quantity'] ?? 1)
                    : null;

                MaintenanceRecordValue::create([
                    'maintenance_record_id' => $maintenance->id,
                    'procedure_field_id' => $field->id,
                    'value' => $value,
                    'quantity' => $quantity,
                ]);

                if (
                    $executionType !== 'internal'
                    || $field->field_type !== 'stock_item'
                    || ! $value
                ) {
                    continue;
                }

                $stockItem = $stockItems->get((int) $value);
                $quantity = (float) $quantity;

                StockMovement::create([
                    'tenant_id' => $vehicle->tenant_id,
                    'location_id' => $vehicle->location_id,
                    'stock_item_id' => $stockItem->id,
                    'maintenance_record_id' => $maintenance->id,
                    'movement_type' => 'out',
                    'quantity' => $quantity,
                    'unit_cost' => $stockItem->unit_cost,
                    'description' => 'Manutenção #'.$maintenance->id,
                ]);

                $stockItem->quantity -= $quantity;
                $stockItem->save();

                $totalCost += $stockItem->unit_cost * $quantity;
                $updatedStockItem = $stockItem;
            }

            $totalCost += $data['extra_cost'] ?? 0;

            $maintenance->update([
                'total_cost' => $totalCost,
            ]);

            $maintenance->load('procedure');

            app(AuditLogService::class)->created($maintenance, [
                'tenant_id' => $vehicle->tenant_id,
                'division_id' => $vehicle->division_id,
                'location_id' => $vehicle->location_id,
                'module' => 'maintenance',
                'summary' => 'Manutencao registrada para o veiculo ' . $vehicle->plate,
                'after_data' => [
                    'maintenance' => $maintenance->toArray(),
                    'values' => $maintenance->values()->get()->toArray(),
                    'stock_usage' => $stockUsage->toArray(),
                    'total_cost' => $totalCost,
                ],
                'metadata' => [
                    'vehicle_id' => $vehicle->id,
                    'procedure_id' => $procedure->id,
                    'maintenance_type' => $executionType,
                ],
            ]);

            return [
                'maintenance' => $maintenance,
                'updated_stock_item' => $updatedStockItem,
            ];
        });
    }

    private static function collectStockUsage(Collection $fields, array $data): Collection
    {
        $fieldValues = $data['fields'] ?? [];

        return $fields
            ->filter(fn ($field) => $field->field_type === 'stock_item')
            ->map(function ($field) use ($fieldValues) {
                $itemId = $fieldValues[$field->slug] ?? null;

                if (! $itemId) {
                    return null;
                }

                $quantity = (float) ($fieldValues[$field->slug.'_quantity'] ?? 1);

                if ($quantity <= 0) {
                    throw ValidationException::withMessages([
                        "fields.{$field->slug}_quantity" =>
                            'A quantidade utilizada deve ser maior que zero.',
                    ]);
                }

                return [
                    'field' => $field,
                    'item_id' => (int) $itemId,
                    'quantity' => $quantity,
                ];
            })
            ->filter()
            ->values();
    }

    private static function lockAndValidateStockItems(
        Collection $stockUsage,
        Vehicle $vehicle
    ): Collection {
        if ($stockUsage->isEmpty()) {
            return collect();
        }

        $itemIds = $stockUsage
            ->pluck('item_id')
            ->unique()
            ->sort()
            ->values();

        $stockItems = StockItem::query()
            ->where('tenant_id', $vehicle->tenant_id)
            ->where('location_id', $vehicle->location_id)
            ->where('active', true)
            ->whereIn('id', $itemIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($stockUsage as $usage) {
            $item = $stockItems->get($usage['item_id']);
            $field = $usage['field'];

            if (
                ! $item
                || ! $field->stock_category_id
                || (int) $item->stock_category_id !== (int) $field->stock_category_id
            ) {
                throw ValidationException::withMessages([
                    "fields.{$field->slug}" =>
                        'O item selecionado não pertence ao estoque disponível para esta unidade.',
                ]);
            }
        }

        foreach ($stockUsage->groupBy('item_id') as $itemId => $usages) {
            $requiredQuantity = $usages->sum('quantity');
            $item = $stockItems->get((int) $itemId);

            if ((float) $item->quantity < $requiredQuantity) {
                throw ValidationException::withMessages([
                    'fields' =>
                        "Saldo insuficiente para o item {$item->name}.",
                ]);
            }
        }

        return $stockItems;
    }

    private static function calculateNextDue(Procedure $procedure, array $data): array
    {
        $nextDueKm = null;
        $nextDueHours = null;
        $nextDueDate = null;

        if ($procedure->validity_km && $procedure->interval_km) {
            $nextDueKm = $data['performed_km'] + $procedure->interval_km;
        }

        if ($procedure->validity_hours && $procedure->interval_hours) {
            $nextDueHours = $data['performed_hours'] + $procedure->interval_hours;
        }

        if ($procedure->validity_period && $procedure->interval_days) {
            $nextDueDate = now()
                ->parse($data['performed_at'])
                ->addDays($procedure->interval_days);
        }

        return [$nextDueKm, $nextDueHours, $nextDueDate];
    }

    private static function stockMovementsForMaintenance(
        MaintenanceRecord $maintenance
    ): Collection {
        return StockMovement::query()
            ->where('tenant_id', $maintenance->tenant_id)
            ->where('movement_type', 'out')
            ->where(function ($query) use ($maintenance) {
                $query
                    ->where('maintenance_record_id', $maintenance->id)
                    ->orWhere(function ($query) use ($maintenance) {
                        $query
                            ->whereNull('maintenance_record_id')
                            ->where('description', 'like', '%#'.$maintenance->id);
                    });
            })
            ->orderBy('id')
            ->get();
    }
}
