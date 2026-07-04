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
use App\Models\MaintenanceRecordStatusLog;
use App\Models\MaintenanceRecordItem;
use App\Models\MaintenanceRecordItemValue;
use App\Models\MaintenanceRecordExtraCost;
use App\Models\VehicleDowntimePeriod;

class MaintenanceService
{
    public static function cancel(
        MaintenanceRecord $maintenance,
        string $reason,
        User $user
    ): MaintenanceRecord {
        return DB::transaction(function () use ($maintenance, $reason, $user) {
            $maintenance = MaintenanceRecord::query()
                ->with(['vehicle', 'items.values'])
                ->whereKey($maintenance->id)
                ->lockForUpdate()
                ->firstOrFail();
    
            if ($maintenance->cancelled_at) {
                throw ValidationException::withMessages([
                    'maintenance' => 'Esta manutenção já foi cancelada.',
                ]);
            }
    
            if ($maintenance->workflow_status === 'closed') {
                throw ValidationException::withMessages([
                    'maintenance' => 'Manutenções encerradas não podem ser canceladas por este fluxo.',
                ]);
            }
    
            $vehicle = $maintenance->vehicle;
    
            if (! $vehicle) {
                throw ValidationException::withMessages([
                    'maintenance' => 'Não foi possível localizar o veículo da manutenção.',
                ]);
            }
    
            $before = $maintenance->toArray();
            $vehicleBefore = $vehicle->toArray();
    
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
                        'maintenance' => 'Não foi possível reverter o estoque consumido pela manutenção.',
                    ]);
                }
    
                $reverseMovement = StockMovement::create([
                    'tenant_id' => $movement->tenant_id,
                    'location_id' => $movement->location_id,
                    'stock_item_id' => $movement->stock_item_id,
                    'maintenance_record_id' => $maintenance->id,
                    'maintenance_record_item_id' => $movement->maintenance_record_item_id,
                    'movement_type' => 'in',
                    'quantity' => $movement->quantity,
                    'unit_cost' => $movement->unit_cost,
                    'total_cost' => (float) $movement->quantity * (float) $movement->unit_cost,
                    'description' => 'Reversão do cancelamento da manutenção #'.$maintenance->id,
                    'reversed_from_movement_id' => $movement->id,
                ]);
    
                $movement->update([
                    'reversal_movement_id' => $reverseMovement->id,
                ]);
    
                $stockItem->quantity = (float) $stockItem->quantity + (float) $movement->quantity;
                $stockItem->save();
    
                $reverseMovements->push($reverseMovement);
    
                app(AuditLogService::class)->created($reverseMovement, [
                    'tenant_id' => $movement->tenant_id,
                    'division_id' => $vehicle->division_id,
                    'location_id' => $movement->location_id,
                    'module' => 'stock',
                    'summary' => 'Movimento reverso criado pelo cancelamento da manutenção #'.$maintenance->id.'.',
                    'after_data' => $reverseMovement->toArray(),
                    'metadata' => [
                        'maintenance_record_id' => $maintenance->id,
                        'maintenance_record_item_id' => $movement->maintenance_record_item_id,
                        'original_stock_movement_id' => $movement->id,
                        'stock_item_id' => $movement->stock_item_id,
                        'quantity_reversed' => $movement->quantity,
                    ],
                    'reason' => $reason,
                ]);
            }
    
            $maintenance->update([
                'workflow_status' => 'cancelled',
                'cancelled_at' => now(),
                'cancelled_by' => $user->id,
                'cancel_reason' => $reason,
            ]);
    
            $vehicle->update([
                'operational_status' => 'operational',
            ]);

            VehicleDowntimePeriod::query()
                ->where('vehicle_id', $vehicle->id)
                ->where('status', 'maintenance')
                ->where('reason', 'like', '%#'.$maintenance->id)
                ->whereNull('ended_at')
                ->latest('started_at')
                ->first()
                ?->update([
                    'ended_at' => now(),
                ]);
    
            $maintenanceAfter = $maintenance->fresh(['vehicle', 'items.values']);
    
            app(AuditLogService::class)->cancelled($maintenanceAfter, [
                'tenant_id' => $vehicle->tenant_id,
                'division_id' => $vehicle->division_id,
                'location_id' => $vehicle->location_id,
                'module' => 'maintenance',
                'summary' => 'Manutenção cancelada para o veículo ' . $vehicle->plate . '.',
                'before_data' => $before,
                'after_data' => [
                    'workflow_status' => $maintenanceAfter->workflow_status,
                    'cancelled_at' => $maintenanceAfter->cancelled_at,
                    'cancelled_by' => $maintenanceAfter->cancelled_by,
                    'cancel_reason' => $maintenanceAfter->cancel_reason,
                ],
                'metadata' => [
                    'vehicle_id' => $vehicle->id,
                    'reverse_stock_movements' => $reverseMovements
                        ->map(fn ($movement) => $movement->toArray())
                        ->values()
                        ->all(),
                ],
                'reason' => $reason,
            ]);
    
            app(AuditLogService::class)->updated($vehicle, [
                'tenant_id' => $vehicle->tenant_id,
                'division_id' => $vehicle->division_id,
                'location_id' => $vehicle->location_id,
                'module' => 'fleet',
                'summary' => 'Veículo liberado após cancelamento da manutenção #'.$maintenanceAfter->id.'.',
                'before_data' => $vehicleBefore,
                'after_data' => $vehicle->fresh()->toArray(),
                'metadata' => [
                    'maintenance_record_id' => $maintenanceAfter->id,
                ],
                'reason' => $reason,
            ]);
    
            return $maintenanceAfter;
        });
    }

    public static function create(array $data, Vehicle $vehicle): array
    {
        return DB::transaction(function () use ($data, $vehicle) {
            $existingOpenMaintenance = MaintenanceRecord::query()
                ->where('tenant_id', $vehicle->tenant_id)
                ->where('vehicle_id', $vehicle->id)
                ->where('workflow_status', 'open')
                ->whereNull('cancelled_at')
                ->lockForUpdate()
                ->first();
    
            if ($existingOpenMaintenance) {
                throw ValidationException::withMessages([
                    'maintenance' => 'Este veículo já possui uma manutenção aberta.',
                ]);
            }
    
            $serviceStatus = $data['service_status']
                ?? self::defaultServiceStatus();
    
            if (! array_key_exists($serviceStatus, self::serviceStatuses())) {
                throw ValidationException::withMessages([
                    'service_status' => 'Status de manutenção inválido.',
                ]);
            }
    
            $startedAt = $data['started_at']
                ?? $data['performed_at']
                ?? now();
            $totalCost = (float) ($data['extra_cost'] ?? 0);
            $maintenance = MaintenanceRecord::create([
                'tenant_id' => $vehicle->tenant_id,
                'vehicle_id' => $vehicle->id,
    
                'procedure_id' => null,
                'maintenance_type' => null,
    
                'performed_km' => $data['performed_km'] ?? null,
                'performed_hours' => $data['performed_hours'] ?? null,
                'performed_at' => $data['performed_at'] ?? $startedAt,    
                
                'started_at' => $startedAt,
                'finished_at' => null,
                'workflow_status' => 'open',
                'service_status' => $serviceStatus,
                'opened_by' => auth()->id(),
    
                'next_due_km' => null,
                'next_due_hours' => null,
                'next_due_date' => null,
    
                'total_cost' => $totalCost,
                'extra_cost' => $totalCost,
                'reason' => $data['reason'] ?? null,
                'provider_name' => null,
                'notes' => $data['notes'] ?? null,
            ]);
            if ((float) ($data['extra_cost'] ?? 0) > 0) {
                MaintenanceRecordExtraCost::create([
                    'maintenance_record_id' => $maintenance->id,
                    'description' => 'Custo inicial da manutenção',
                    'amount' => (float) $data['extra_cost'],
                    'created_by' => auth()->id(),
                ]);
            }
    
            MaintenanceRecordStatusLog::create([
                'maintenance_record_id' => $maintenance->id,
                'old_status' => null,
                'new_status' => $serviceStatus,
                'changed_by' => auth()->id(),
                'reason' => 'Abertura da manutenção.',
            ]);
    
            $vehicleBefore = $vehicle->toArray();
    
            $vehicle->update([
                'operational_status' => 'maintenance',
                'status_reason' => 'Manutenção aberta #'.$maintenance->id,
                'status_changed_at' => $startedAt,
            ]);
            
            VehicleDowntimePeriod::query()
                ->where('vehicle_id', $vehicle->id)
                ->whereNull('ended_at')
                ->latest('started_at')
                ->first()
                ?->update([
                    'ended_at' => now(),
                ]);
            
            VehicleDowntimePeriod::create([
                'vehicle_id' => $vehicle->id,
                'status' => 'maintenance',
                'reason' => 'Manutenção aberta #'.$maintenance->id,
                'started_at' => $startedAt,
            ]);
    
            app(AuditLogService::class)->updated($vehicle, [
                'tenant_id' => $vehicle->tenant_id,
                'division_id' => $vehicle->division_id,
                'location_id' => $vehicle->location_id,
                'module' => 'fleet',
                'summary' => 'Veículo colocado em manutenção pela abertura da manutenção #'.$maintenance->id.'.',
                'before_data' => $vehicleBefore,
                'after_data' => $vehicle->fresh()->toArray(),
                'metadata' => [
                    'maintenance_record_id' => $maintenance->id,
                    'service_status' => $serviceStatus,
                ],
            ]);
    
            app(AuditLogService::class)->created($maintenance, [
                'tenant_id' => $vehicle->tenant_id,
                'division_id' => $vehicle->division_id,
                'location_id' => $vehicle->location_id,
                'module' => 'maintenance',
                'summary' => 'Manutenção aberta para o veículo ' . $vehicle->plate . '.',
                'after_data' => [
                    'maintenance' => $maintenance->toArray(),
                ],
                'metadata' => [
                    'vehicle_id' => $vehicle->id,
                    'workflow_status' => 'open',
                    'service_status' => $serviceStatus,
                ],
            ]);
    
            return [
                'maintenance' => $maintenance,
                'updated_stock_item' => null,
            ];
        });
    }
    
    public static function addItem(
        MaintenanceRecord $maintenance,
        array $data
    ): MaintenanceRecordItem {
        return DB::transaction(function () use ($maintenance, $data) {
    
            $maintenance = MaintenanceRecord::query()
                ->with(['vehicle'])
                ->whereKey($maintenance->id)
                ->lockForUpdate()
                ->firstOrFail();
    
            if ($maintenance->workflow_status !== 'open') {
                throw ValidationException::withMessages([
                    'maintenance' => 'Somente manutenções abertas aceitam novos procedimentos.',
                ]);
            }
    
            $vehicle = $maintenance->vehicle;
    
            $procedure = Procedure::with('fields')
                ->where('tenant_id', $vehicle->tenant_id)
                ->where('location_id', $vehicle->location_id)
                ->findOrFail($data['procedure_id']);
    
            $executionType = $data['maintenance_type'] ?? 'external';
    
            $stockUsage = collect();
            $stockItems = collect();
    
            if ($executionType === 'internal') {
                $stockUsage = self::collectStockUsage($procedure->fields, $data);
                $stockItems = self::lockAndValidateStockItems(
                    $stockUsage,
                    $vehicle
                );
            }
            
            [$nextDueKm, $nextDueHours, $nextDueDate] =
            self::calculateNextDue($procedure, $data);
            
            $item = MaintenanceRecordItem::create([
                'maintenance_record_id' => $maintenance->id,
                'procedure_id' => $procedure->id,
                'maintenance_type' => $executionType,
    
                'performed_km' =>
                    $data['performed_km']
                    ?? $maintenance->performed_km,
    
                'performed_hours' =>
                    $data['performed_hours']
                    ?? $maintenance->performed_hours,
    
                'performed_at' =>
                    $data['performed_at']
                    ?? $maintenance->performed_at,
                    
                'next_due_km' => $nextDueKm,
                'next_due_hours' => $nextDueHours,
                'next_due_date' => $nextDueDate,
    
                'total_cost' => 0,
                'extra_cost' => $data['extra_cost'] ?? 0,
                'provider_name' =>
                    $executionType === 'external'
                        ? ($data['provider_name'] ?? null)
                        : null,
                'notes' => $data['notes'] ?? null,
            ]);
    
            $totalCost = 0;
            $fieldValues = $data['fields'] ?? [];
    
            foreach ($procedure->fields as $field) {
                $value = $fieldValues[$field->slug] ?? null;
    
                $quantity = $field->field_type === 'stock_item'
                    ? ($fieldValues[$field->slug.'_quantity'] ?? 1)
                    : 1;
    
                MaintenanceRecordItemValue::create([
                    'maintenance_record_item_id' => $item->id,
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
                    'maintenance_record_item_id' => $item->id,
    
                    'movement_type' => 'out',
                    'quantity' => $quantity,
                    'unit_cost' => $stockItem->unit_cost,
                    'total_cost' => $quantity * $stockItem->unit_cost,
                    'description' =>
                        'Item #'.$item->id.
                        ' da manutenção #'.$maintenance->id,
                ]);
    
                $stockItem->quantity -= $quantity;
                $stockItem->save();
    
                $totalCost += $stockItem->unit_cost * $quantity;
            }
    
            $totalCost += $data['extra_cost'] ?? 0;
    
            $item->update([
                'total_cost' => $totalCost,
            ]);
    
            $maintenance->update([
                'total_cost' =>
                    (float) $maintenance->total_cost + $totalCost,
            ]);
    
            return $item->fresh(['procedure', 'values']);
        });
    }

    public static function changeStatus(
        MaintenanceRecord $maintenance,
        string $newStatus,
        ?string $reason = null
    ): MaintenanceRecord {
        return DB::transaction(function () use ($maintenance, $newStatus, $reason) {
            if (! array_key_exists($newStatus, self::serviceStatuses())) {
                throw ValidationException::withMessages([
                    'service_status' => 'Status de manutenção inválido.',
                ]);
            }
    
            $maintenance = MaintenanceRecord::query()
                ->with(['vehicle', 'procedure'])
                ->whereKey($maintenance->id)
                ->lockForUpdate()
                ->firstOrFail();
    
            if ($maintenance->workflow_status !== 'open') {
                throw ValidationException::withMessages([
                    'maintenance' => 'Somente manutenções abertas podem ter o status alterado.',
                ]);
            }
    
            if ($maintenance->cancelled_at) {
                throw ValidationException::withMessages([
                    'maintenance' => 'Manutenções canceladas não podem ter o status alterado.',
                ]);
            }
    
            $oldStatus = $maintenance->service_status;
    
            if ($oldStatus === $newStatus) {
                throw ValidationException::withMessages([
                    'service_status' => 'A manutenção já está neste status.',
                ]);
            }
    
            $before = $maintenance->toArray();
    
            $maintenance->update([
                'service_status' => $newStatus,
            ]);
    
            MaintenanceRecordStatusLog::create([
                'maintenance_record_id' => $maintenance->id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'changed_by' => auth()->id(),
                'reason' => $reason,
            ]);
    
            $maintenanceAfter = $maintenance->fresh(['vehicle', 'procedure']);
    
            app(AuditLogService::class)->updated($maintenanceAfter, [
                'tenant_id' => $maintenanceAfter->tenant_id,
                'division_id' => $maintenanceAfter->vehicle?->division_id,
                'location_id' => $maintenanceAfter->vehicle?->location_id,
                'module' => 'maintenance',
                'summary' => 'Status da manutenção #'.$maintenanceAfter->id.' alterado.',
                'before_data' => $before,
                'after_data' => $maintenanceAfter->toArray(),
                'metadata' => [
                    'vehicle_id' => $maintenanceAfter->vehicle_id,
                    'procedure_id' => $maintenanceAfter->procedure_id,
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                ],
                'reason' => $reason,
            ]);
    
            return $maintenanceAfter;
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
    
        if (
            $procedure->validity_km
            && $procedure->interval_km
            && isset($data['performed_km'])
            && $data['performed_km'] !== null
        ) {
            $nextDueKm = (int) $data['performed_km'] + (int) $procedure->interval_km;
        }
    
        if (
            $procedure->validity_hours
            && $procedure->interval_hours
            && isset($data['performed_hours'])
            && $data['performed_hours'] !== null
        ) {
            $nextDueHours = (int) $data['performed_hours'] + (int) $procedure->interval_hours;
        }
    
        if (
            $procedure->validity_period
            && $procedure->interval_days
            && ! empty($data['performed_at'])
        ) {
            $nextDueDate = now()
                ->parse($data['performed_at'])
                ->addDays((int) $procedure->interval_days);
        }
    
        return [
            $nextDueKm,
            $nextDueHours,
            $nextDueDate,
        ];
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

    public static function serviceStatuses(): array
    {
        return [
            'technical_analysis' => 'Análise técnica',
            'service_in_progress' => 'Andamento do serviço',
            'awaiting_material' => 'Aguardando material',
            'material_survey' => 'Levantamento material',
            'purchase_request' => 'Solicitação de compra',
            'awaiting_labor' => 'Aguardando mão de obra',
            'awaiting_resource' => 'Aguardando recurso',
            'awaiting_approval' => 'Pendente de aprovação',
            'awaiting_budget' => 'Pendente de orçamento',
            'supplier_warranty' => 'Garantia do fornecedor',
            'third_party_responsibility' => 'Responsabilidade terceiros',
            'scheduled_commitment' => 'Compromisso lançado',
        ];
    }
    
    public static function defaultServiceStatus(): string
    {
        return 'technical_analysis';
    }
    public static function close(
        MaintenanceRecord $maintenance,
        string $vehicleStatusAfter,
        ?string $closureNotes = null
    ): MaintenanceRecord {
        return DB::transaction(function () use ($maintenance, $vehicleStatusAfter, $closureNotes) {
            $maintenance = MaintenanceRecord::query()
                ->with(['vehicle'])
                ->whereKey($maintenance->id)
                ->lockForUpdate()
                ->firstOrFail();
    
            if (! in_array($vehicleStatusAfter, [
                'operational',
                'inactive',
                'inoperant',
                'accident',
                'support',
                'testing',
                'transfer',
                'transferred',
            ], true)) {
                throw ValidationException::withMessages([
                    'vehicle_status_after' => 'Status final do veículo inválido.',
                ]);
            }
    
            if ($maintenance->workflow_status !== 'open') {
                throw ValidationException::withMessages([
                    'maintenance' => 'Somente manutenções abertas podem ser encerradas.',
                ]);
            }
    
            if ($maintenance->cancelled_at) {
                throw ValidationException::withMessages([
                    'maintenance' => 'Manutenções canceladas não podem ser encerradas.',
                ]);
            }
    
            if ($maintenance->items()->count() === 0) {
                throw ValidationException::withMessages([
                    'maintenance' => 'Adicione ao menos um procedimento antes de encerrar a manutenção.',
                ]);
            }
    
            $vehicle = $maintenance->vehicle;
            $before = $maintenance->toArray();
            $vehicleBefore = $vehicle->toArray();
    
            $maintenance->update([
                'workflow_status' => 'closed',
                'finished_at' => now(),
                'closed_by' => auth()->id(),
                'closure_notes' => $closureNotes,
            ]);
    
            $statusReason = $closureNotes
                ?: 'Manutenção encerrada #'.$maintenance->id;
            
            $vehicle->update([
                'operational_status' => $vehicleStatusAfter,
                'status_reason' => $statusReason,
                'status_changed_at' => now(),
            ]);
            
            VehicleDowntimePeriod::query()
                ->where('vehicle_id', $vehicle->id)
                ->whereNull('ended_at')
                ->latest('started_at')
                ->first()
                ?->update([
                    'ended_at' => now(),
                ]);
            
            if ($vehicleStatusAfter !== 'operational') {
                VehicleDowntimePeriod::create([
                    'vehicle_id' => $vehicle->id,
                    'status' => $vehicleStatusAfter,
                    'reason' => $statusReason,
                    'started_at' => now(),
                ]);
            }
    
            $maintenanceAfter = $maintenance->fresh(['vehicle', 'items']);
    
            app(AuditLogService::class)->updated($maintenanceAfter, [
                'tenant_id' => $maintenanceAfter->tenant_id,
                'division_id' => $vehicle->division_id,
                'location_id' => $vehicle->location_id,
                'module' => 'maintenance',
                'summary' => 'Manutenção #'.$maintenanceAfter->id.' encerrada.',
                'before_data' => $before,
                'after_data' => $maintenanceAfter->toArray(),
                'metadata' => [
                    'vehicle_id' => $vehicle->id,
                    'total_cost' => $maintenanceAfter->total_cost,
                    'finished_at' => $maintenanceAfter->finished_at,
                ],
                'reason' => $closureNotes,
            ]);
    
            app(AuditLogService::class)->updated($vehicle, [
                'tenant_id' => $vehicle->tenant_id,
                'division_id' => $vehicle->division_id,
                'location_id' => $vehicle->location_id,
                'module' => 'fleet',
                'summary' => 'Veículo liberado após encerramento da manutenção #'.$maintenanceAfter->id.'.',
                'before_data' => $vehicleBefore,
                'after_data' => $vehicle->fresh()->toArray(),
                'metadata' => [
                    'maintenance_record_id' => $maintenanceAfter->id,
                ],
            ]);
    
            return $maintenanceAfter;
        });
    }

    public static function addExtraCost(
        MaintenanceRecord $maintenance,
        array $data
    ): MaintenanceRecordExtraCost {
        return DB::transaction(function () use ($maintenance, $data) {
            $maintenance = MaintenanceRecord::query()
                ->whereKey($maintenance->id)
                ->lockForUpdate()
                ->firstOrFail();
    
            if ($maintenance->workflow_status !== 'open') {
                throw ValidationException::withMessages([
                    'maintenance' => 'Somente manutenções abertas aceitam custos avulsos.',
                ]);
            }
    
            if ($maintenance->cancelled_at) {
                throw ValidationException::withMessages([
                    'maintenance' => 'Manutenções canceladas não aceitam custos avulsos.',
                ]);
            }
    
            $extraCost = MaintenanceRecordExtraCost::create([
                'maintenance_record_id' => $maintenance->id,
                'description' => $data['description'],
                'amount' => $data['amount'],
                'created_by' => auth()->id(),
            ]);
    
            $maintenance->update([
                'total_cost' => (float) $maintenance->total_cost + (float) $extraCost->amount,
            ]);
    
            return $extraCost;
        });
    }    
}
