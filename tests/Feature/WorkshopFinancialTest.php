<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\Location;
use App\Models\ProfilePermissionOverride;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserDivisionAccess;
use App\Models\WorkshopExpense;
use App\Services\WorkshopConsumptionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkshopFinancialTest extends TestCase
{
    use RefreshDatabase;

    public function test_consumption_supports_decimal_quantity_and_keeps_other_location_intact(): void
    {
        [$user, $location] = $this->context();
        $this->actingAs($user)->withSession(['active_division_id'=>$location->division_id,'active_location_id'=>$location->id]);
        session(['active_division_id'=>$location->division_id,'active_location_id'=>$location->id]);
        $item = StockItem::create(['tenant_id'=>$user->tenant_id,'location_id'=>$location->id,'name'=>'Graxa','unit'=>'L','quantity'=>20,'minimum_quantity'=>0,'unit_cost'=>20,'active'=>true,'is_workshop_consumable'=>true]);
        $other = StockItem::create(['tenant_id'=>$user->tenant_id,'location_id'=>$this->otherLocation($user)->id,'name'=>'Graxa externa','unit'=>'L','quantity'=>20,'minimum_quantity'=>0,'unit_cost'=>20,'active'=>true]);

        app(WorkshopConsumptionService::class)->record($item, 1.25, Carbon::now()->toDateString(), 'Teste', $user);

        $this->assertDatabaseHas('stock_movements', ['stock_item_id'=>$item->id,'movement_type'=>'out','quantity'=>1.25,'unit_cost'=>20,'total_cost'=>25]);
        $this->assertSame(StockMovement::WORKSHOP_CONSUMPTION_PREFIX.' Teste', StockMovement::first()->description);
        $this->assertSame(18.75, (float) $item->fresh()->quantity);
        $this->assertSame(20.0, (float) $other->fresh()->quantity);
    }

    public function test_workshop_expenses_are_scoped_to_the_active_location(): void
    {
        [$user, $location] = $this->context();
        $other = $this->otherLocation($user);
        WorkshopExpense::create(['tenant_id'=>$user->tenant_id,'division_id'=>$location->division_id,'location_id'=>$location->id,'expense_date'=>Carbon::now(),'category'=>'tools','description'=>'Local','amount'=>50,'created_by'=>$user->id]);
        WorkshopExpense::create(['tenant_id'=>$user->tenant_id,'division_id'=>$other->division_id,'location_id'=>$other->id,'expense_date'=>Carbon::now(),'category'=>'tools','description'=>'Externo','amount'=>99,'created_by'=>$user->id]);

        $this->actingAs($user)->withSession(['active_division_id'=>$location->division_id,'active_location_id'=>$location->id])->get('/workshop')->assertOk()->assertSee('R$ 50,00')->assertDontSee('R$ 99,00');
    }

    public function test_expense_registration_does_not_create_maintenance_or_stock_movement(): void
    {
        [$user, $location] = $this->context();
        $this->actingAs($user)->withSession(['active_division_id'=>$location->division_id,'active_location_id'=>$location->id])
            ->post(route('workshop.expenses.store'), ['expense_date'=>now()->toDateString(),'category'=>'tools','description'=>'Teste ferramenta oficina','supplier_name'=>'Fornecedor Teste','invoice_number'=>'TESTE-001','amount'=>100,'notes'=>null])
            ->assertRedirect();
        $this->assertDatabaseHas('workshop_expenses', ['location_id'=>$location->id,'description'=>'Teste ferramenta oficina','amount'=>100]);
        $this->assertDatabaseCount('maintenance_records', 0);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_consumption_request_rejects_an_item_from_another_location(): void
    {
        [$user, $location] = $this->context();
        $outside = StockItem::create(['tenant_id'=>$user->tenant_id,'location_id'=>$this->otherLocation($user)->id,'name'=>'Item externo','unit'=>'L','quantity'=>20,'minimum_quantity'=>0,'unit_cost'=>20,'active'=>true]);

        $this->actingAs($user)->withSession(['active_division_id'=>$location->division_id,'active_location_id'=>$location->id])
            ->post(route('workshop.consumption.store'), ['stock_item_id'=>$outside->id,'quantity'=>1.25,'moved_at'=>now()->toDateString()])
            ->assertNotFound();

        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertSame(20.0, (float) $outside->fresh()->quantity);
    }

    public function test_only_available_workshop_consumables_are_exposed_and_false_items_are_rejected(): void
    {
        [$user, $location] = $this->context();
        $allowed = StockItem::create(['tenant_id'=>$user->tenant_id,'location_id'=>$location->id,'name'=>'Graxa permitida','unit'=>'L','quantity'=>2,'minimum_quantity'=>0,'unit_cost'=>20,'active'=>true,'is_workshop_consumable'=>true]);
        $blocked = StockItem::create(['tenant_id'=>$user->tenant_id,'location_id'=>$location->id,'name'=>'Peça comum','unit'=>'UN','quantity'=>2,'minimum_quantity'=>0,'unit_cost'=>20,'active'=>true]);
        StockItem::create(['tenant_id'=>$user->tenant_id,'location_id'=>$location->id,'name'=>'Sem saldo','unit'=>'L','quantity'=>0,'minimum_quantity'=>0,'unit_cost'=>20,'active'=>true,'is_workshop_consumable'=>true]);

        $this->actingAs($user)->withSession(['active_division_id'=>$location->division_id,'active_location_id'=>$location->id])->get('/workshop')->assertOk()->assertSee('Graxa permitida')->assertDontSee('Peça comum');
        $this->actingAs($user)->withSession(['active_division_id'=>$location->division_id,'active_location_id'=>$location->id])->post(route('workshop.consumption.store'), ['stock_item_id'=>$blocked->id,'quantity'=>1,'moved_at'=>now()->toDateString()])->assertNotFound();
        $this->assertFalse((bool) $blocked->is_workshop_consumable);
        $this->assertTrue((bool) $allowed->is_workshop_consumable);
    }

    public function test_expense_update_and_soft_delete_require_specific_permissions_and_keep_audit_history(): void
    {
        [$user, $location] = $this->context();
        $expense = WorkshopExpense::create(['tenant_id'=>$user->tenant_id,'division_id'=>$location->division_id,'location_id'=>$location->id,'expense_date'=>now(),'category'=>'tools','description'=>'Original','amount'=>10,'created_by'=>$user->id]);
        $session = ['active_division_id'=>$location->division_id,'active_location_id'=>$location->id];
        $payload = ['expense_date'=>now()->toDateString(),'category'=>'tools','description'=>'Alterada','amount'=>20];
        $this->actingAs($user)->withSession($session)->put(route('workshop.expenses.update', $expense), $payload)->assertForbidden();
        $this->grant($user, $location, 'workshop.expenses.update');
        $this->actingAs($user)->withSession($session)->put(route('workshop.expenses.update', $expense), $payload)->assertRedirect();
        $this->assertDatabaseHas('workshop_expenses', ['id'=>$expense->id,'description'=>'Alterada','amount'=>20]);
        $this->grant($user, $location, 'workshop.expenses.delete');
        $this->actingAs($user)->withSession($session)->delete(route('workshop.expenses.destroy', $expense))->assertRedirect();
        $this->assertSoftDeleted('workshop_expenses', ['id'=>$expense->id]);
        $this->assertDatabaseHas('system_audit_logs', ['auditable_id'=>$expense->id,'action'=>'deleted','module'=>'workshop']);
    }

    public function test_consumption_deletion_reverses_stock_once_and_cannot_be_repeated(): void
    {
        [$user, $location] = $this->context();
        $session = ['active_division_id'=>$location->division_id,'active_location_id'=>$location->id];
        $this->actingAs($user)->withSession($session); session($session);
        $item = StockItem::create(['tenant_id'=>$user->tenant_id,'location_id'=>$location->id,'name'=>'Graxa','unit'=>'L','quantity'=>10,'minimum_quantity'=>0,'unit_cost'=>12,'active'=>true,'is_workshop_consumable'=>true]);
        $movement = app(WorkshopConsumptionService::class)->record($item, 2, now()->toDateString(), 'Uso interno', $user);
        $this->grant($user, $location, 'workshop.consumptions.delete');
        $this->actingAs($user)->withSession($session)->delete(route('workshop.consumption.destroy', $movement))->assertRedirect();
        $this->assertSame(10.0, (float) $item->fresh()->quantity);
        $this->assertNotNull($movement->fresh()->cancelled_at);
        $this->assertDatabaseHas('stock_movements', ['reversed_from_movement_id'=>$movement->id,'movement_type'=>'in','quantity'=>2]);
        $this->actingAs($user)->withSession($session)->delete(route('workshop.consumption.destroy', $movement))->assertSessionHasErrors('consumption');
        $this->assertDatabaseCount('stock_movements', 2);
    }

    public function test_consumption_correction_reverses_original_before_creating_the_replacement(): void
    {
        [$user, $location] = $this->context();
        $session = ['active_division_id'=>$location->division_id,'active_location_id'=>$location->id];
        $this->actingAs($user)->withSession($session); session($session);
        $item = StockItem::create(['tenant_id'=>$user->tenant_id,'location_id'=>$location->id,'name'=>'Óleo','unit'=>'L','quantity'=>10,'minimum_quantity'=>0,'unit_cost'=>10,'active'=>true,'is_workshop_consumable'=>true]);
        $original = app(WorkshopConsumptionService::class)->record($item, 2, now()->toDateString(), 'Original', $user);
        $this->grant($user, $location, 'workshop.consumptions.update');
        $this->actingAs($user)->withSession($session)->put(route('workshop.consumption.update', $original), ['stock_item_id'=>$item->id,'quantity'=>3,'moved_at'=>now()->toDateString(),'notes'=>'Corrigido'])->assertRedirect();
        $this->assertNotNull($original->fresh()->cancelled_at);
        $this->assertSame(7.0, (float) $item->fresh()->quantity);
        $this->assertDatabaseHas('stock_movements', ['reversed_from_movement_id'=>$original->id,'movement_type'=>'in','quantity'=>2]);
        $this->assertDatabaseHas('stock_movements', ['movement_type'=>'out','quantity'=>3,'description'=>StockMovement::WORKSHOP_CONSUMPTION_PREFIX.' Corrigido']);
    }

    private function context(): array
    {
        $tenant=Tenant::create(['name'=>'Oficina']); $division=Division::create(['tenant_id'=>$tenant->id,'name'=>'Divisão']); $location=Location::create(['tenant_id'=>$tenant->id,'division_id'=>$division->id,'name'=>'Local']); User::factory()->create(['tenant_id'=>$tenant->id]); $user=User::factory()->create(['tenant_id'=>$tenant->id]); UserDivisionAccess::create(['tenant_id'=>$tenant->id,'user_id'=>$user->id,'division_id'=>$division->id,'location_id'=>$location->id,'module'=>'fleet','profile'=>'supervisor','active'=>true]); return [$user,$location];
    }
    private function otherLocation(User $user): Location { return Location::firstOrCreate(['tenant_id'=>$user->tenant_id,'division_id'=>Division::first()->id,'name'=>'Outro local']); }
    private function grant(User $user, Location $location, string $permission): void { ProfilePermissionOverride::updateOrCreate(['tenant_id'=>$user->tenant_id,'division_id'=>$location->division_id,'location_id'=>$location->id,'module'=>'fleet','profile'=>'supervisor','permission_key'=>$permission], ['allowed'=>true,'created_by'=>$user->id,'updated_by'=>$user->id]); }
}
