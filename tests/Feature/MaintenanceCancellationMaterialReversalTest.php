<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\Location;
use App\Models\MaintenanceMaterialUsage;
use App\Models\MaintenanceRecord;
use App\Models\StockCategory;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserDivisionAccess;
use App\Models\Vehicle;
use App\Services\MaintenanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaintenanceCancellationMaterialReversalTest extends TestCase
{
    use RefreshDatabase;

    public function test_cancelling_om_reverses_only_its_active_consumption_and_audits_it(): void
    {
        [$user, $maintenance, $item] = $this->context(5);
        $usage = $this->usage($maintenance, $item, 2);
        $other = $this->maintenance($maintenance->vehicle, $maintenance->tenant_id);
        $otherUsage = $this->usage($other, $item, 1);

        MaintenanceService::cancel($maintenance, 'Cancelamento de teste da ordem de manutenção.', $user);

        $this->assertSame(4.0, (float) $item->fresh()->quantity);
        $this->assertNotNull($usage->fresh()->cancelled_at);
        $this->assertNotNull($usage->stockMovement->fresh()->cancelled_at);
        $this->assertDatabaseHas('stock_movements', ['reversed_from_movement_id' => $usage->stock_movement_id, 'movement_type' => 'in', 'quantity' => 2]);
        $this->assertNull($otherUsage->fresh()->cancelled_at);
        $this->assertDatabaseHas('system_audit_logs', ['module' => 'maintenance', 'action' => 'reversed', 'auditable_id' => $usage->stock_movement_id]);
    }

    public function test_direct_purchase_entry_is_preserved_while_its_consumption_is_reversed(): void
    {
        [$user, $maintenance, $item] = $this->context(0);
        $entry = StockMovement::create(['tenant_id'=>$maintenance->tenant_id, 'location_id'=>$item->location_id, 'stock_item_id'=>$item->id, 'maintenance_record_id'=>$maintenance->id, 'movement_type'=>'in', 'quantity'=>2, 'unit_cost'=>10, 'total_cost'=>20, 'description'=>'Compra direta para manutenção #'.$maintenance->id, 'moved_at'=>now()]);
        $item->increment('quantity', 2);
        $usage = $this->usage($maintenance, $item, 2, $entry->id);

        MaintenanceService::cancel($maintenance, 'Cancelamento de compra direta da OM.', $user);

        $this->assertSame(2.0, (float) $item->fresh()->quantity);
        $this->assertNull($entry->fresh()->cancelled_at);
        $this->assertNull($entry->fresh()->reversal_movement_id);
        $this->assertNotNull($usage->fresh()->cancelled_at);
        $this->assertDatabaseHas('stock_movements', ['reversed_from_movement_id'=>$usage->stock_movement_id, 'movement_type'=>'in']);
    }

    public function test_second_cancellation_is_rejected_without_creating_another_return(): void
    {
        [$user, $maintenance, $item] = $this->context(2);
        $this->usage($maintenance, $item, 1);
        MaintenanceService::cancel($maintenance, 'Primeiro cancelamento da ordem.', $user);
        $count = StockMovement::count();
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        try { MaintenanceService::cancel($maintenance, 'Segunda tentativa de cancelamento.', $user); } finally { $this->assertSame($count, StockMovement::count()); }
    }

    private function context(float $quantity): array
    {
        $tenant=Tenant::create(['name'=>'Tenant']); $division=Division::create(['tenant_id'=>$tenant->id,'name'=>'Divisão']); $location=Location::create(['tenant_id'=>$tenant->id,'division_id'=>$division->id,'name'=>'Unidade']); $user=User::factory()->create(['tenant_id'=>$tenant->id]); UserDivisionAccess::create(['tenant_id'=>$tenant->id,'user_id'=>$user->id,'division_id'=>$division->id,'location_id'=>$location->id,'module'=>'fleet','profile'=>'manager','active'=>true]); $this->actingAs($user)->withSession(['active_division_id'=>$division->id,'active_location_id'=>$location->id]); $vehicle=Vehicle::create(['tenant_id'=>$tenant->id,'division_id'=>$division->id,'location_id'=>$location->id,'name'=>'Veículo','plate'=>'ABC1234','type'=>'automovel','operational_status'=>'maintenance']); $category=StockCategory::create(['tenant_id'=>$tenant->id,'name'=>'Peças']); $item=StockItem::create(['tenant_id'=>$tenant->id,'location_id'=>$location->id,'stock_category_id'=>$category->id,'name'=>'Peça','unit'=>'UN','quantity'=>$quantity,'minimum_quantity'=>0,'unit_cost'=>10]); return [$user,$this->maintenance($vehicle,$tenant->id),$item];
    }

    private function maintenance(Vehicle $vehicle, int $tenantId): MaintenanceRecord { return MaintenanceRecord::create(['tenant_id'=>$tenantId,'vehicle_id'=>$vehicle->id,'maintenance_type'=>'internal','performed_at'=>now(),'workflow_status'=>'open']); }
    private function usage(MaintenanceRecord $maintenance, StockItem $item, int $quantity, ?int $purchaseEntryId = null): MaintenanceMaterialUsage { $movement=StockMovement::create(['tenant_id'=>$maintenance->tenant_id,'location_id'=>$item->location_id,'stock_item_id'=>$item->id,'maintenance_record_id'=>$maintenance->id,'movement_type'=>'out','quantity'=>$quantity,'unit_cost'=>10,'total_cost'=>$quantity*10,'description'=>'Material utilizado diretamente na manutenção #'.$maintenance->id,'moved_at'=>now()]); $item->decrement('quantity',$quantity); return MaintenanceMaterialUsage::create(['tenant_id'=>$maintenance->tenant_id,'location_id'=>$item->location_id,'maintenance_record_id'=>$maintenance->id,'stock_item_id'=>$item->id,'stock_movement_id'=>$movement->id,'purchase_entry_movement_id'=>$purchaseEntryId,'quantity'=>$quantity,'unit_cost'=>10,'total_cost'=>$quantity*10,'used_at'=>now()]); }
}
