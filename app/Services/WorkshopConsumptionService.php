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
}
