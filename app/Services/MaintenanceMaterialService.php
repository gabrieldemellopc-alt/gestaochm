<?php

namespace App\Services;

use App\Models\MaintenanceMaterialUsage;
use App\Models\MaintenanceRecord;
use App\Models\MaintenanceRecordItem;
use App\Models\StockItem;
use App\Models\StockCategory;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\StockEntryService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MaintenanceMaterialService
{
    public function addDirectPurchase(MaintenanceRecord $maintenance, array $data, User $user, StockEntryService $entries): MaintenanceMaterialUsage
    {
        return DB::transaction(function () use ($maintenance, $data, $user, $entries) {
            $maintenance = $this->editable($maintenance);
            $locationId = $maintenance->vehicle->location_id;
            $item = null;
            if (! empty($data['stock_item_id'])) {
                $item = StockItem::query()->whereKey($data['stock_item_id'])
                    ->where('tenant_id', $maintenance->tenant_id)->where('location_id', $locationId)
                    ->where('active', true)->lockForUpdate()->first();
                if (! $item) {
                    throw ValidationException::withMessages(['stock_item_id' => 'O item selecionado não está disponível neste estoque.']);
                }
            } else {
                if (empty($data['stock_category_id'])) {
                    throw ValidationException::withMessages(['stock_category_id' => 'Informe uma categoria para o novo item.']);
                }
                if (! StockCategory::query()->whereKey($data['stock_category_id'])->where('tenant_id', $maintenance->tenant_id)->exists()) {
                    throw ValidationException::withMessages(['stock_category_id' => 'A categoria selecionada não pertence a este contexto.']);
                }
                $item = StockItem::query()->where('tenant_id', $maintenance->tenant_id)->where('location_id', $locationId)
                    ->where('name', $data['name'])->where('unit', $data['unit'])->where('brand', $data['brand'] ?? null)
                    ->where('stock_category_id', $data['stock_category_id'] ?? null)->lockForUpdate()->first();
            }
            if (! $item) $item = StockItem::create(['tenant_id'=>$maintenance->tenant_id,'location_id'=>$locationId,'name'=>$data['name'],'brand'=>$data['brand']??null,'stock_category_id'=>$data['stock_category_id']??null,'unit'=>$data['unit'],'quantity'=>0,'unit_cost'=>0,'minimum_quantity'=>0,'active'=>true,'observation'=>'Criado por compra direta da manutenção #'.$maintenance->id]);
            $total = round((float) $data['total_cost'], 2);
            $entry = $entries->record($item, ['quantity'=>$data['quantity'],'unit_cost'=>$data['unit_cost'],'total_cost'=>$total,'supplier_name'=>$data['supplier_name']??null,'invoice_number'=>$data['invoice_number']??null,'description'=>'Compra direta para manutenção #'.$maintenance->id,'moved_at'=>now()]);
            $entry->update([
                'maintenance_record_id' => $maintenance->id,
                'maintenance_record_item_id' => $data['maintenance_record_item_id'] ?? null,
            ]);
            $usage = $this->createUsage($maintenance, [
                'stock_item_id' => $item->id,
                'maintenance_record_item_id' => $data['maintenance_record_item_id'] ?? null,
                'quantity' => $data['quantity'],
                'notes' => $data['notes'] ?? null,
            ], $user);
            $usage->update(['purchase_entry_movement_id' => $entry->id]);
            MaintenanceService::recalculateTotalCost($maintenance);
            $this->audit($usage, 'created', 'maintenance_material_direct_purchase', null, ['stock_entry_movement_id'=>$entry->id]);
            return $usage;
        });
    }
    public function search(MaintenanceRecord $maintenance, string $term = ''): Collection
    {
        return StockItem::query()
            ->with('category:id,name')
            ->where('tenant_id', $maintenance->tenant_id)
            ->where('location_id', $maintenance->vehicle->location_id)
            ->where('active', true)
            ->when($term !== '', fn ($query) => $query->where(function ($query) use ($term) {
                $query->where('name', 'like', "%{$term}%")
                    ->orWhere('brand', 'like', "%{$term}%")
                    ->orWhereHas('category', fn ($category) => $category->where('name', 'like', "%{$term}%"));
            }))
            ->orderBy('name')->limit(20)->get();
    }

    public function add(MaintenanceRecord $maintenance, array $data, User $user): MaintenanceMaterialUsage
    {
        $data['quantity'] = self::validatedQuantity($data['quantity'] ?? null);

        return DB::transaction(function () use ($maintenance, $data, $user) {
            $maintenance = $this->editable($maintenance);
            $usage = $this->createUsage($maintenance, $data, $user);
            MaintenanceService::recalculateTotalCost($maintenance);
            $this->audit($usage, 'created', 'maintenance_material_used');
            return $usage->fresh(['stockItem.category', 'creator']);
        });
    }

    public function cancel(MaintenanceRecord $maintenance, MaintenanceMaterialUsage $usage, string $reason, User $user): void
    {
        DB::transaction(function () use ($maintenance, $usage, $reason, $user) {
            $maintenance = $this->editable($maintenance);
            $usage = $this->activeUsage($maintenance, $usage);
            $this->reverse($maintenance, $usage, $reason, $user);
            MaintenanceService::recalculateTotalCost($maintenance);
            $this->audit($usage->fresh(), 'cancelled', 'maintenance_material_cancelled', $reason);
        });
    }

    public function replace(MaintenanceRecord $maintenance, MaintenanceMaterialUsage $usage, array $data, User $user): MaintenanceMaterialUsage
    {
        $data['quantity'] = self::validatedQuantity($data['quantity'] ?? null);

        return DB::transaction(function () use ($maintenance, $usage, $data, $user) {
            $maintenance = $this->editable($maintenance);
            $usage = $this->activeUsage($maintenance, $usage);
            $this->reverse($maintenance, $usage, $data['change_reason'], $user);
            $replacement = $this->createUsage($maintenance, $data, $user, $usage->id);
            $usage->update(['replaced_by_usage_id' => $replacement->id]);
            MaintenanceService::recalculateTotalCost($maintenance);
            $this->audit($replacement, 'updated', 'maintenance_material_replaced', $data['change_reason'], [
                'replaced_usage_id' => $usage->id,
            ]);
            return $replacement->fresh(['stockItem.category', 'creator']);
        });
    }

    private function createUsage(MaintenanceRecord $maintenance, array $data, User $user, ?int $replaces = null): MaintenanceMaterialUsage
    {
        $maintenanceItemId = $data['maintenance_record_item_id'] ?? null;
        if ($maintenanceItemId) {
            MaintenanceRecordItem::query()
                ->whereKey($maintenanceItemId)
                ->where('maintenance_record_id', $maintenance->id)
                ->whereNull('cancelled_at')
                ->lockForUpdate()
                ->firstOrFail();
        }
        $item = StockItem::query()
            ->whereKey($data['stock_item_id'])
            ->where('tenant_id', $maintenance->tenant_id)
            ->where('location_id', $maintenance->vehicle->location_id)
            ->where('active', true)->lockForUpdate()->firstOrFail();
        $quantity = self::validatedQuantity($data['quantity'] ?? null);
        if ((float) $item->quantity < $quantity) {
            throw ValidationException::withMessages(['quantity' => 'Saldo insuficiente para a quantidade informada.']);
        }
        $unitCost = (float) $item->unit_cost;
        $movement = StockMovement::create([
            'tenant_id' => $maintenance->tenant_id,
            'location_id' => $maintenance->vehicle->location_id,
            'stock_item_id' => $item->id,
            'maintenance_record_id' => $maintenance->id,
            'maintenance_record_item_id' => $maintenanceItemId,
            'movement_type' => 'out', 'quantity' => $quantity,
            'unit_cost' => $unitCost, 'total_cost' => round($quantity * $unitCost, 2),
            'description' => 'Material utilizado diretamente na manutenção #'.$maintenance->id,
            'moved_at' => now(),
        ]);
        $item->decrement('quantity', $quantity);
        return MaintenanceMaterialUsage::create([
            'tenant_id' => $maintenance->tenant_id,
            'location_id' => $maintenance->vehicle->location_id,
            'maintenance_record_id' => $maintenance->id,
            'maintenance_record_item_id' => $maintenanceItemId,
            'stock_item_id' => $item->id, 'stock_movement_id' => $movement->id,
            'quantity' => $quantity, 'unit_cost' => $unitCost,
            'total_cost' => round($quantity * $unitCost, 2),
            'notes' => $data['notes'] ?? null, 'created_by' => $user->id,
            'replaces_usage_id' => $replaces,
        ]);
    }

    public static function validatedQuantity(mixed $quantity): int
    {
        $validated = filter_var($quantity, FILTER_VALIDATE_INT);

        if ($validated === false || $validated < 1) {
            throw ValidationException::withMessages([
                'quantity' => 'Informe uma quantidade inteira igual ou maior que 1.',
            ]);
        }

        return $validated;
    }

    private function reverse(MaintenanceRecord $maintenance, MaintenanceMaterialUsage $usage, string $reason, User $user): void
    {
        $item = StockItem::query()->whereKey($usage->stock_item_id)->lockForUpdate()->firstOrFail();
        $movement = StockMovement::query()->whereKey($usage->stock_movement_id)->lockForUpdate()->firstOrFail();
        $reverse = StockMovement::create([
            'tenant_id' => $usage->tenant_id, 'location_id' => $usage->location_id,
            'stock_item_id' => $usage->stock_item_id, 'maintenance_record_id' => $maintenance->id,
            'maintenance_record_item_id' => $usage->maintenance_record_item_id, 'movement_type' => 'in',
            'quantity' => $usage->quantity, 'unit_cost' => $usage->unit_cost,
            'total_cost' => $usage->total_cost, 'reversed_from_movement_id' => $movement->id,
            'description' => 'Devolução de material da manutenção #'.$maintenance->id.': '.$reason,
            'moved_at' => now(),
        ]);
        $movement->update([
            'cancelled_at' => now(), 'cancelled_by' => $user->id,
            'cancel_reason' => $reason, 'reversal_movement_id' => $reverse->id,
        ]);
        $usage->update([
            'cancelled_at' => now(), 'cancelled_by' => $user->id,
            'cancel_reason' => $reason, 'reversal_movement_id' => $reverse->id,
        ]);
        $item->increment('quantity', (float) $usage->quantity);
    }

    private function editable(MaintenanceRecord $maintenance): MaintenanceRecord
    {
        $maintenance = MaintenanceRecord::query()->with('vehicle')->whereKey($maintenance->id)
            ->whereNull('deleted_at')->lockForUpdate()->firstOrFail();
        if ($maintenance->cancelled_at || $maintenance->workflow_status !== 'open') {
            throw ValidationException::withMessages(['maintenance' => 'Somente manutenções abertas aceitam materiais.']);
        }
        return $maintenance;
    }

    private function activeUsage(MaintenanceRecord $maintenance, MaintenanceMaterialUsage $usage): MaintenanceMaterialUsage
    {
        return MaintenanceMaterialUsage::query()->whereKey($usage->id)
            ->where('maintenance_record_id', $maintenance->id)->whereNull('cancelled_at')
            ->lockForUpdate()->firstOrFail();
    }

    private function audit(MaintenanceMaterialUsage $usage, string $action, string $event, ?string $reason = null, array $extra = []): void
    {
        app(AuditLogService::class)->record([
            'auditable' => $usage, 'action' => $action, 'module' => 'maintenance',
            'summary' => config('chm_labels.audit_action.'.$event, $event), 'reason' => $reason,
            'after_data' => $usage->toArray(),
            'metadata' => array_merge([
                'event' => $event, 'maintenance_record_id' => $usage->maintenance_record_id,
                'vehicle_id' => $usage->maintenanceRecord?->vehicle_id,
                'stock_item_id' => $usage->stock_item_id,
                'stock_movement_id' => $usage->stock_movement_id,
                'quantity' => (float) $usage->quantity, 'unit_cost' => (float) $usage->unit_cost,
                'total_cost' => (float) $usage->total_cost, 'user_id' => auth()->id(),
            ], $extra),
        ]);
    }
}
