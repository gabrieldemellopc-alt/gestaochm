<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\FuelProduct;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserDivisionAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleCreateFuelProductsTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_returns_ok_and_receives_only_the_tenant_configurable_primary_fuel_products(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant de teste']);
        $division = Division::create(['tenant_id' => $tenant->id, 'name' => 'Divisão de teste']);
        $location = Location::create([
            'tenant_id' => $tenant->id,
            'division_id' => $division->id,
            'name' => 'Unidade de teste',
            'active' => true,
        ]);
        User::factory()->create(['tenant_id' => $tenant->id]); // Preserva o bypass intencional do usuário 1.
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        UserDivisionAccess::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'division_id' => $division->id,
            'location_id' => $location->id,
            'module' => 'fleet',
            'profile' => 'manager',
            'active' => true,
        ]);

        foreach (['diesel-s10' => 'Diesel S10', 'diesel-s500' => 'Diesel S500', 'alcool' => 'Álcool', 'gasolina' => 'Gasolina'] as $slug => $name) {
            FuelProduct::create([
                'tenant_id' => $tenant->id,
                'name' => $name,
                'slug' => $slug,
                'unit' => 'litros',
                'active' => true,
            ]);
        }

        FuelProduct::create(['tenant_id' => $tenant->id, 'name' => 'ARLA 32', 'slug' => 'arla', 'unit' => 'litros', 'active' => true]);
        FuelProduct::create(['tenant_id' => $tenant->id, 'name' => 'Diesel', 'slug' => 'diesel', 'unit' => 'litros', 'active' => true]);
        FuelProduct::create(['tenant_id' => $tenant->id, 'name' => 'Gasolina inativa', 'slug' => 'gasolina-inativa', 'unit' => 'litros', 'active' => false]);

        $otherTenant = Tenant::create(['name' => 'Outro tenant']);
        FuelProduct::create(['tenant_id' => $otherTenant->id, 'name' => 'Diesel S10 externo', 'slug' => 'diesel-s10', 'unit' => 'litros', 'active' => true]);

        $response = $this->actingAs($user)
            ->withSession([
                'active_division_id' => $division->id,
                'active_location_id' => $location->id,
            ])
            ->get('/vehicles/create');

        $response->assertOk()
            ->assertViewIs('vehicle.create')
            ->assertViewHas('fuelProducts', function ($products) use ($tenant) {
                return $products->pluck('slug')->all() === ['diesel-s10', 'diesel-s500', 'alcool', 'gasolina']
                    && $products->every(fn (FuelProduct $product) => $product->tenant_id === $tenant->id);
            });
    }
}
