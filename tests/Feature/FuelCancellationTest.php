<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\FuelFilling;
use App\Models\FuelMovement;
use App\Models\FuelProduct;
use App\Models\FuelReceipt;
use App\Models\FuelTank;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserDivisionAccess;
use App\Models\Vehicle;
use App\Models\VehicleUpdateLog;
use App\Services\FuelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FuelCancellationTest extends TestCase
{
    use RefreshDatabase;

    private array $context;

    protected function setUp(): void
    {
        parent::setUp();
        $tenant = Tenant::create(['name' => 'Teste combustível']);
        $division = Division::create(['tenant_id' => $tenant->id, 'name' => 'Divisão']);
        $location = Location::create(['tenant_id' => $tenant->id, 'division_id' => $division->id, 'name' => 'Unidade', 'active' => true]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        UserDivisionAccess::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'division_id' => $division->id, 'location_id' => $location->id, 'module' => 'fleet', 'profile' => 'manager', 'active' => true]);
        $this->actingAs($user);
        session(['active_division_id' => $division->id, 'active_location_id' => $location->id]);
        $this->context = compact('tenant', 'division', 'location', 'user');
    }

    public function test_internal_filling_is_reversed_once_and_its_reading_is_ignored(): void
    {
        $tank = $this->tank(1000, 5000); $vehicle = $this->vehicle(900);
        VehicleUpdateLog::create(['vehicle_id' => $vehicle->id, 'user_id' => $this->context['user']->id, 'division_id' => $this->context['division']->id, 'location_id' => $this->context['location']->id, 'type' => 'km', 'source' => 'manual', 'new_value' => 900, 'read_at' => now()->subMinute(), 'reading_status' => VehicleUpdateLog::READING_STATUS_VALID]);
        $filling = app(FuelService::class)->registerFilling($this->fillingData($tank, $vehicle, 100, 1000));
        $log = VehicleUpdateLog::where('fuel_filling_id', $filling->id)->firstOrFail();

        app(FuelService::class)->cancelFilling($filling, 'Lançamento incorreto');
        $tank->refresh(); $filling->refresh(); $log->refresh(); $vehicle->refresh();
        $this->assertNotNull($filling->cancelled_at); $this->assertSame($this->context['user']->id, $filling->cancelled_by);
        $this->assertSame('Lançamento incorreto', $filling->cancel_reason);
        $this->assertEquals(1000, $tank->current_balance_liters); $this->assertEquals(5000, $tank->estimated_stock_value); $this->assertEquals(5, $tank->average_unit_cost);
        $this->assertSame(VehicleUpdateLog::READING_STATUS_IGNORED, $log->reading_status);
        $this->assertSame(900.0, (float) $vehicle->current_km);
        $this->assertSame(1, FuelMovement::where('movement_type', FuelMovement::TYPE_REVERSAL)->count());

        try { app(FuelService::class)->cancelFilling($filling, 'Tentativa duplicada'); $this->fail('Esperava validação.'); } catch (ValidationException) {}
        $tank->refresh(); $this->assertEquals(1000, $tank->current_balance_liters); $this->assertSame(1, FuelMovement::where('movement_type', FuelMovement::TYPE_REVERSAL)->count());
    }

    public function test_external_filling_does_not_change_tank(): void
    {
        $tank = $this->tank(1000, 5000); $vehicle = $this->vehicle(800); $product = $tank->product;
        $filling = FuelFilling::create(['tenant_id' => $this->context['tenant']->id, 'division_id' => $this->context['division']->id, 'location_id' => $this->context['location']->id, 'fuel_product_id' => $product->id, 'vehicle_id' => $vehicle->id, 'source' => FuelFilling::SOURCE_EXTERNAL_STATION, 'filled_at' => now(), 'vehicle_km' => 900, 'quantity_liters' => 50, 'unit_cost' => 6, 'total_cost' => 300, 'responsible_user_id' => $this->context['user']->id]);
        VehicleUpdateLog::create(['vehicle_id' => $vehicle->id, 'user_id' => $this->context['user']->id, 'division_id' => $this->context['division']->id, 'location_id' => $this->context['location']->id, 'type' => 'km', 'source' => 'fuel_filling', 'fuel_filling_id' => $filling->id, 'new_value' => 900, 'read_at' => now()]);
        app(FuelService::class)->cancelFilling($filling, 'Posto incorreto');
        $tank->refresh(); $this->assertEquals(1000, $tank->current_balance_liters); $this->assertSame(0, FuelMovement::where('movement_type', FuelMovement::TYPE_REVERSAL)->count());
    }

    public function test_receipt_reversal_restores_financial_balance_and_blocks_unsafe_cases(): void
    {
        $tank = $this->tank(1000, 5000); $receipt = app(FuelService::class)->receiveFuel(['fuel_tank_id' => $tank->id, 'received_at' => now(), 'quantity_liters' => 500, 'total_cost' => 3000]);
        app(FuelService::class)->cancelReceipt($receipt, 'Documento duplicado');
        $tank->refresh(); $this->assertEquals(1000, $tank->current_balance_liters); $this->assertEquals(5000, $tank->estimated_stock_value); $this->assertEquals(5, $tank->average_unit_cost);

        $unsafe = FuelReceipt::create(['tenant_id' => $this->context['tenant']->id, 'division_id' => $this->context['division']->id, 'location_id' => $this->context['location']->id, 'fuel_tank_id' => $tank->id, 'fuel_product_id' => $tank->fuel_product_id, 'received_at' => now(), 'quantity_liters' => 1200, 'total_cost' => 6000, 'responsible_user_id' => $this->context['user']->id]);
        try { app(FuelService::class)->cancelReceipt($unsafe, 'Saldo insuficiente'); $this->fail('Esperava validação.'); } catch (ValidationException) {}
        $this->assertNull($unsafe->fresh()->cancelled_at);
    }

    private function tank(float $liters, float $value): FuelTank { $p = FuelProduct::create(['tenant_id'=>$this->context['tenant']->id,'name'=>'Diesel','slug'=>'diesel','unit'=>'L','active'=>true]); return FuelTank::create(['tenant_id'=>$this->context['tenant']->id,'division_id'=>$this->context['division']->id,'location_id'=>$this->context['location']->id,'fuel_product_id'=>$p->id,'name'=>'Tanque','capacity_liters'=>3000,'current_balance_liters'=>$liters,'estimated_stock_value'=>$value,'average_unit_cost'=>5,'minimum_balance_liters'=>0,'active'=>true]); }
    private function vehicle(float $km): Vehicle { return Vehicle::create(['tenant_id'=>$this->context['tenant']->id,'division_id'=>$this->context['division']->id,'location_id'=>$this->context['location']->id,'name'=>'Veículo','plate'=>'AAA0001','type'=>'lixo','operational_status'=>'operational','current_km'=>$km]); }
    private function fillingData(FuelTank $tank, Vehicle $vehicle, float $liters, float $total): array { return ['source'=>'internal_tank','fuel_tank_id'=>$tank->id,'vehicle_id'=>$vehicle->id,'filled_at'=>now(),'vehicle_km'=>1000,'quantity_liters'=>$liters,'total_cost'=>$total,'km_reading_confirmed'=>true]; }
}
