<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\Location;
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

    private function context(): array
    {
        $tenant=Tenant::create(['name'=>'Oficina']); $division=Division::create(['tenant_id'=>$tenant->id,'name'=>'Divisão']); $location=Location::create(['tenant_id'=>$tenant->id,'division_id'=>$division->id,'name'=>'Local']); User::factory()->create(['tenant_id'=>$tenant->id]); $user=User::factory()->create(['tenant_id'=>$tenant->id]); UserDivisionAccess::create(['tenant_id'=>$tenant->id,'user_id'=>$user->id,'division_id'=>$division->id,'location_id'=>$location->id,'module'=>'fleet','profile'=>'supervisor','active'=>true]); return [$user,$location];
    }
    private function otherLocation(User $user): Location { return Location::firstOrCreate(['tenant_id'=>$user->tenant_id,'division_id'=>Division::first()->id,'name'=>'Outro local']); }
}
