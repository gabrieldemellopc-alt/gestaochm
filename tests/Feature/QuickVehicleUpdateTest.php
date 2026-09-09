<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserDivisionAccess;
use App\Models\Vehicle;
use App\Models\VehicleUpdateLog;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuickVehicleUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_relation_filters_scope_the_list_and_kpis(): void
    {
        [$user, $location] = $this->context();
        $internal = $this->vehicle($location, 'INTERNO', 'internal');
        $aggregated = $this->vehicle($location, 'AGREGADO', 'aggregated');
        $rented = $this->vehicle($location, 'ALUGADO', 'rented');

        $this->inContext($user, $location)->get(route('vehicle.quick-update'))
            ->assertOk()->assertViewHas('vehicles', fn ($vehicles) => $vehicles->count() === 3)->assertSee($internal->name)->assertSee($aggregated->name)->assertSee($rented->name);
        $this->inContext($user, $location)->get(route('vehicle.quick-update', ['fleet_relation' => 'internal']))
            ->assertOk()->assertViewHas('vehicles', fn ($vehicles) => $vehicles->count() === 1)->assertSee($internal->name)->assertDontSee($aggregated->name)->assertDontSee($rented->name);
        $this->inContext($user, $location)->get(route('vehicle.quick-update', ['fleet_relation' => 'aggregated']))
            ->assertOk()->assertViewHas('vehicles', fn ($vehicles) => $vehicles->count() === 1)->assertSee($aggregated->name)->assertDontSee($internal->name)->assertDontSee($rented->name);
        $this->inContext($user, $location)->get(route('vehicle.quick-update', ['fleet_relation' => 'rented']))
            ->assertOk()->assertViewHas('vehicles', fn ($vehicles) => $vehicles->count() === 1)->assertSee($rented->name)->assertDontSee($internal->name)->assertDontSee($aggregated->name);
    }

    public function test_confirmation_updates_only_enabled_current_readings_and_creates_logs(): void
    {
        [$user, $location] = $this->context();
        $vehicle = $this->vehicle($location, 'CONFIRMACAO', 'internal', [
            'current_km' => 596201,
            'current_hours' => 15,
            'km_control_enabled' => true,
            'hours_control_enabled' => true,
            'last_km_update_at' => now()->subDays(20),
            'last_hours_update_at' => now()->subDays(20),
        ]);

        $this->inContext($user, $location)->post(route('vehicle.quick-update.store'), [
            'vehicles' => [[
                'id' => $vehicle->id,
                'current_km' => 596201,
                'current_hours' => 15,
                'confirmed' => true,
            ]],
        ])->assertRedirect();

        $vehicle->refresh();
        $this->assertSame(596201.0, (float) $vehicle->current_km);
        $this->assertSame(15.0, (float) $vehicle->current_hours);
        $this->assertTrue(Carbon::parse($vehicle->last_km_update_at)->isToday());
        $this->assertTrue(Carbon::parse($vehicle->last_hours_update_at)->isToday());
        $this->assertSame(2, VehicleUpdateLog::where('vehicle_id', $vehicle->id)->where('source', 'quick_update_confirmation')->count());
    }

    public function test_real_change_with_confirmation_does_not_duplicate_its_log_and_regression_remains_blocked(): void
    {
        [$user, $location] = $this->context();
        $vehicle = $this->vehicle($location, 'SEM DUPLICIDADE', 'internal', ['current_km' => 100, 'current_hours' => 0]);

        $this->inContext($user, $location)->post(route('vehicle.quick-update.store'), [
            'vehicles' => [['id' => $vehicle->id, 'current_km' => 120, 'confirmed' => true]],
        ])->assertRedirect();
        $this->assertSame(1, VehicleUpdateLog::where('vehicle_id', $vehicle->id)->where('type', 'km')->count());
        $this->assertSame('dashboard_quick_update', VehicleUpdateLog::where('vehicle_id', $vehicle->id)->value('source'));

        $this->inContext($user, $location)->from(route('vehicle.quick-update'))->post(route('vehicle.quick-update.store'), [
            'vehicles' => [['id' => $vehicle->id, 'current_km' => 99]],
        ])->assertSessionHasErrors('vehicles.'.$vehicle->id.'.current_km');
    }

    public function test_disabled_controls_and_other_location_vehicle_are_not_changed(): void
    {
        [$user, $location] = $this->context();
        $disabled = $this->vehicle($location, 'DESATIVADO', 'internal', ['current_km' => 10, 'current_hours' => 5, 'km_control_enabled' => false, 'hours_control_enabled' => false]);
        $otherLocation = Location::create(['tenant_id' => $location->tenant_id, 'division_id' => $location->division_id, 'name' => 'Outra unidade', 'active' => true]);
        $outside = $this->vehicle($otherLocation, 'FORA DO ESCOPO', 'internal', ['current_km' => 20]);

        $this->inContext($user, $location)->post(route('vehicle.quick-update.store'), [
            'vehicles' => [
                ['id' => $disabled->id, 'current_km' => 99, 'current_hours' => 99, 'confirmed' => true],
                ['id' => $outside->id, 'current_km' => 99, 'confirmed' => true],
            ],
        ])->assertRedirect();

        $this->assertSame(10.0, (float) $disabled->fresh()->current_km);
        $this->assertSame(5.0, (float) $disabled->fresh()->current_hours);
        $this->assertSame(20.0, (float) $outside->fresh()->current_km);
        $this->assertSame(0, VehicleUpdateLog::count());
    }

    private function context(): array
    {
        $tenant = Tenant::create(['name' => 'Tenant']);
        $division = Division::create(['tenant_id' => $tenant->id, 'name' => 'Divisão']);
        $location = Location::create(['tenant_id' => $tenant->id, 'division_id' => $division->id, 'name' => 'Imperatriz', 'active' => true]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        UserDivisionAccess::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'division_id' => $division->id, 'location_id' => $location->id, 'module' => 'fleet', 'profile' => 'manager', 'active' => true]);

        return [$user, $location];
    }

    private function vehicle(Location $location, string $name, string $relation, array $attributes = []): Vehicle
    {
        return Vehicle::create(array_merge([
            'tenant_id' => $location->tenant_id,
            'division_id' => $location->division_id,
            'location_id' => $location->id,
            'name' => $name,
            'plate' => 'P'.str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT),
            'type' => 'automovel',
            'fleet_relation' => $relation,
            'status' => 'active',
            'operational_status' => 'operational',
            'current_km' => 0,
            'current_hours' => 0,
            'km_control_enabled' => true,
            'hours_control_enabled' => false,
            'tire_control_enabled' => true,
        ], $attributes));
    }

    private function inContext(User $user, Location $location): self
    {
        return $this->actingAs($user)->withSession([
            'active_division_id' => $location->division_id,
            'active_location_id' => $location->id,
        ]);
    }
}
