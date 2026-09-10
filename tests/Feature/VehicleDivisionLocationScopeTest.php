<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\Location;
use App\Models\ProfilePermissionOverride;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserDivisionAccess;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleDivisionLocationScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_supervisor_with_aksa_only_sees_only_aksa_on_create_and_edit_and_cannot_open_bsm_vehicle(): void
    {
        $context = $this->context();
        $supervisor = $this->supervisor($context, [$context['aksaLocation']]);

        $this->actingInAksa($supervisor, $context)
            ->get('/vehicles/create')
            ->assertOk()
            ->assertDontSee('BSM')
            ->assertViewHas('divisions', fn ($divisions) => $divisions->pluck('id')->all() === [$context['aksa']->id])
            ->assertViewHas('locations', fn ($locations) => $locations->pluck('id')->all() === [$context['aksaLocation']->id]);

        $vehicle = $this->vehicle($context, $context['aksa'], $context['aksaLocation']);
        $this->actingInAksa($supervisor, $context)
            ->get(route('vehicles.edit', $vehicle))
            ->assertOk()
            ->assertDontSee('BSM')
            ->assertViewHas('divisions', fn ($divisions) => $divisions->pluck('id')->all() === [$context['aksa']->id]);

        $bsmVehicle = $this->vehicle($context, $context['bsm'], $context['bsmLocation']);
        $this->actingInAksa($supervisor, $context)
            ->get(route('vehicles.edit', $bsmVehicle))
            ->assertForbidden();
    }

    public function test_supervisor_with_two_divisions_sees_both_and_admin_bypass_sees_all_tenant_locations(): void
    {
        $context = $this->context();
        $supervisor = $this->supervisor($context, [$context['aksaLocation'], $context['bsmLocation']]);

        $this->actingInAksa($supervisor, $context)
            ->get('/vehicles/create')
            ->assertOk()
            ->assertViewHas('divisions', fn ($divisions) => $divisions->pluck('id')->all() === [$context['aksa']->id, $context['bsm']->id]);

        $admin = User::factory()->create(['tenant_id' => $context['tenant']->id]);
        UserDivisionAccess::create([
            'tenant_id' => $context['tenant']->id,
            'user_id' => $admin->id,
            'division_id' => $context['aksa']->id,
            'location_id' => $context['aksaLocation']->id,
            'module' => 'fleet',
            'profile' => 'admin',
            'active' => true,
        ]);

        $this->actingInAksa($admin, $context)
            ->get('/vehicles/create')
            ->assertOk()
            ->assertViewHas('divisions', fn ($divisions) => $divisions->pluck('id')->all() === [$context['aksa']->id, $context['bsm']->id])
            ->assertViewHas('locations', fn ($locations) => $locations->pluck('id')->sort()->values()->all() === collect([
                $context['aksaLocation']->id,
                $context['aksaOtherLocation']->id,
                $context['bsmLocation']->id,
            ])->sort()->values()->all());
    }

    public function test_create_rejects_a_division_or_location_outside_the_supervisor_scope(): void
    {
        $context = $this->context();
        $supervisor = $this->supervisor($context, [$context['aksaLocation']]);

        $this->actingInAksa($supervisor, $context)
            ->post('/vehicles', $this->payload($context['bsm'], $context['bsmLocation']))
            ->assertForbidden();

        $this->actingInAksa($supervisor, $context)
            ->post('/vehicles', $this->payload($context['aksa'], $context['aksaOtherLocation']))
            ->assertForbidden();
    }

    public function test_update_rejects_moves_to_an_unauthorized_division_or_location(): void
    {
        $context = $this->context();
        $supervisor = $this->supervisor($context, [$context['aksaLocation']]);
        $vehicle = $this->vehicle($context, $context['aksa'], $context['aksaLocation']);

        $this->actingInAksa($supervisor, $context)
            ->put(route('vehicles.update', $vehicle), $this->payload($context['bsm'], $context['bsmLocation']))
            ->assertForbidden();

        $this->actingInAksa($supervisor, $context)
            ->put(route('vehicles.update', $vehicle), $this->payload($context['aksa'], $context['aksaOtherLocation']))
            ->assertForbidden();
    }

    public function test_scope_does_not_leak_across_tenants(): void
    {
        $context = $this->context();
        $supervisor = $this->supervisor($context, [$context['aksaLocation']]);
        $otherTenant = Tenant::create(['name' => 'Outro tenant']);
        $otherDivision = Division::create(['tenant_id' => $otherTenant->id, 'name' => 'Externa']);
        $otherLocation = Location::create(['tenant_id' => $otherTenant->id, 'division_id' => $otherDivision->id, 'name' => 'Externa']);

        $this->actingInAksa($supervisor, $context)
            ->post('/vehicles', $this->payload($otherDivision, $otherLocation))
            ->assertSessionHasErrors(['division_id', 'location_id']);
    }

    private function context(): array
    {
        $tenant = Tenant::create(['name' => 'Tenant frota']);
        $aksa = Division::create(['tenant_id' => $tenant->id, 'name' => 'AKSA']);
        $bsm = Division::create(['tenant_id' => $tenant->id, 'name' => 'BSM']);
        $aksaLocation = Location::create(['tenant_id' => $tenant->id, 'division_id' => $aksa->id, 'name' => 'AKSA Matriz', 'active' => true]);
        $aksaOtherLocation = Location::create(['tenant_id' => $tenant->id, 'division_id' => $aksa->id, 'name' => 'AKSA Filial', 'active' => true]);
        $bsmLocation = Location::create(['tenant_id' => $tenant->id, 'division_id' => $bsm->id, 'name' => 'BSM Matriz', 'active' => true]);

        User::factory()->create(['tenant_id' => $tenant->id]);

        return compact('tenant', 'aksa', 'bsm', 'aksaLocation', 'aksaOtherLocation', 'bsmLocation');
    }

    private function supervisor(array $context, array $locations): User
    {
        $user = User::factory()->create(['tenant_id' => $context['tenant']->id]);

        foreach ($locations as $location) {
            UserDivisionAccess::create([
                'tenant_id' => $context['tenant']->id,
                'user_id' => $user->id,
                'division_id' => $location->division_id,
                'location_id' => $location->id,
                'module' => 'fleet',
                'profile' => 'supervisor',
                'active' => true,
            ]);
        }

        foreach (['vehicles.create', 'vehicles.update'] as $permission) {
            ProfilePermissionOverride::create([
                'tenant_id' => $context['tenant']->id,
                'division_id' => $context['aksa']->id,
                'location_id' => $context['aksaLocation']->id,
                'module' => 'fleet',
                'profile' => 'supervisor',
                'permission_key' => $permission,
                'allowed' => true,
            ]);
        }

        return $user;
    }

    private function actingInAksa(User $user, array $context): static
    {
        return $this->actingAs($user)->withSession([
            'active_division_id' => $context['aksa']->id,
            'active_location_id' => $context['aksaLocation']->id,
        ]);
    }

    private function vehicle(array $context, Division $division, Location $location): Vehicle
    {
        return Vehicle::create([
            'tenant_id' => $context['tenant']->id,
            'division_id' => $division->id,
            'location_id' => $location->id,
            'name' => 'Veículo de teste',
            'plate' => 'TES-' . str_pad((string) Vehicle::query()->count(), 4, '0', STR_PAD_LEFT),
            'type' => 'automovel',
            'status' => 'active',
            'operational_status' => 'operational',
            'current_km' => 0,
            'current_hours' => 0,
        ]);
    }

    private function payload(Division $division, Location $location): array
    {
        return [
            'division_id' => $division->id,
            'location_id' => $location->id,
            'type' => 'automovel',
            'fleet_relation' => 'internal',
            'name' => 'Veículo atualizado',
            'plate' => 'ABC-1D23',
            'status' => 'active',
            'operational_status' => 'operational',
            'km_control_enabled' => true,
            'hours_control_enabled' => false,
            'tire_control_enabled' => true,
            'current_km' => 0,
            'current_hours' => 0,
        ];
    }
}
