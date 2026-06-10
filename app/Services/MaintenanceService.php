<?php

namespace App\Services;

use App\Models\MaintenanceRecord;
use App\Models\MaintenanceRecordValue;
use App\Models\Procedure;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\Vehicle;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MaintenanceService
{
    public static function create(array $data, Vehicle $vehicle): array
    {
        return DB::transaction(function () use ($data, $vehicle) {
            $procedure = Procedure::with('fields')
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
}
