<?php

namespace App\Services;

use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WorkshopConsumptionService
{

    public function record(StockItem $item, float $quantity, string $movedAt, ?string $notes, User $user): StockMovement
    {
        return DB::transaction(function () use ($item, $quantity, $movedAt, $notes, $user) {
            $item = StockItem::query()->whereKey($item->id)->where('tenant_id', $user->tenant_id)->lockForUpdate()->firstOrFail();
            $activeLocation = app(ActiveContextService::class)->activeLocation($user);
            if (! $activeLocation || (int) $item->location_id !== (int) $activeLocation->id) throw ValidationException::withMessages(['stock_item_id' => 'O item não pertence à unidade ativa.']);
            if (! $item->is_workshop_consumable) throw ValidationException::withMessages(['stock_item_id' => 'O item não está habilitado como consumível da oficina.']);
            $quantity = round($quantity, 2);
            if ($quantity <= 0 || $quantity > (float) $item->quantity) throw ValidationException::withMessages(['quantity' => 'Quantidade indisponível em estoque.']);
            $unitCost = round((float) $item->unit_cost, 2); $total = round($quantity * $unitCost, 2);
            $movement = StockMovement::create(['tenant_id'=>$item->tenant_id,'location_id'=>$item->location_id,'stock_item_id'=>$item->id,'movement_type'=>'out','quantity'=>$quantity,'unit_cost'=>$unitCost,'total_cost'=>$total,'description'=>StockMovement::WORKSHOP_CONSUMPTION_PREFIX.' '.trim((string) $notes),'moved_at'=>$movedAt]);
            $item->decrement('quantity', $quantity);
            app(AuditLogService::class)->created($movement, ['tenant_id'=>$item->tenant_id,'location_id'=>$item->location_id,'module'=>'workshop','summary'=>'Consumo interno da oficina registrado.','after_data'=>$movement->toArray()]);
            return $movement;
        });
    }

    /** Reverte sem apagar o lançamento original, devolvendo o saldo por nova entrada vinculada. */
    public function reverse(StockMovement $movement, User $user, string $reason = 'Consumo da oficina excluído'): StockMovement
    {
        return DB::transaction(function () use ($movement, $user, $reason) {
            $movement = StockMovement::query()->whereKey($movement->id)->where('tenant_id', $user->tenant_id)
                ->where('movement_type', 'out')->where('description', 'like', StockMovement::WORKSHOP_CONSUMPTION_PREFIX.'%')
                ->lockForUpdate()->firstOrFail();
            $activeLocation = app(ActiveContextService::class)->activeLocation($user);
            if (! $activeLocation || (int) $movement->location_id !== (int) $activeLocation->id) abort(404);
            if ($movement->cancelled_at || $movement->reversal_movement_id) throw ValidationException::withMessages(['consumption' => 'Este consumo já foi revertido.']);
            $item = StockItem::query()->whereKey($movement->stock_item_id)->where('tenant_id', $user->tenant_id)->lockForUpdate()->firstOrFail();
            $beforeQuantity = (float) $item->quantity;
            $reverse = StockMovement::create(['tenant_id'=>$movement->tenant_id,'location_id'=>$movement->location_id,'stock_item_id'=>$movement->stock_item_id,'movement_type'=>'in','quantity'=>$movement->quantity,'unit_cost'=>$movement->unit_cost,'total_cost'=>$movement->total_cost,'reversed_from_movement_id'=>$movement->id,'description'=>'Reversão de consumo da oficina #'.$movement->id.': '.$reason,'moved_at'=>now()]);
            $movement->update(['cancelled_at'=>now(),'cancelled_by'=>$user->id,'cancel_reason'=>$reason,'reversal_movement_id'=>$reverse->id]);
            $item->increment('quantity', (float) $movement->quantity);
            app(AuditLogService::class)->reversed($movement, ['tenant_id'=>$movement->tenant_id,'location_id'=>$movement->location_id,'module'=>'workshop','summary'=>'Consumo interno da oficina revertido.','before_data'=>['movement'=>$movement->getOriginal(),'stock_quantity'=>$beforeQuantity],'after_data'=>['reversal_movement_id'=>$reverse->id,'stock_quantity'=>(float) $item->fresh()->quantity],'metadata'=>['original_stock_movement_id'=>$movement->id,'reversal_stock_movement_id'=>$reverse->id]]);
            return $reverse;
        });
    }

    public function replace(StockMovement $movement, StockItem $item, float $quantity, string $movedAt, ?string $notes, User $user): StockMovement
    {
        return DB::transaction(function () use ($movement, $item, $quantity, $movedAt, $notes, $user) {
            $this->reverse($movement, $user, 'Consumo corrigido');
            return $this->record($item, $quantity, $movedAt, $notes, $user);
        });
    }
}
