<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\Location;
use App\Models\ProfilePermissionOverride;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserDivisionAccess;
use App\Models\Vehicle;
use App\Services\Permissions\ProfilePermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfilePermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_explicit_false_override_denies_vehicle_pages_and_default_can_be_restored(): void
    {
        [$user, $manager, $division, $location, $vehicle] = $this->supervisorContext();
        $scope = ['division_id' => $division->id, 'location_id' => $location->id, 'module' => 'fleet'];

        $this->actingAs($user)->withSession([
            'active_division_id' => $division->id,
            'active_location_id' => $location->id,
        ])->get('/vehicles')->assertOk();

        ProfilePermissionOverride::create([
            'tenant_id' => $user->tenant_id,
            ...$scope,
            'profile' => 'supervisor',
            'permission_key' => 'vehicles.view',
            'allowed' => false,
        ]);
        ProfilePermissionOverride::create([
            'tenant_id' => $user->tenant_id,
            ...$scope,
            'profile' => 'supervisor',
            'permission_key' => 'navigation.vehicles',
            'allowed' => false,
        ]);

        $permissions = app(ProfilePermissionService::class);
        $this->assertFalse($permissions->allows($user, 'vehicles.view', $scope));
        $this->assertFalse($permissions->allows($user, 'navigation.vehicles', $scope));

        $this->actingAs($user)->withSession([
            'active_division_id' => $division->id,
            'active_location_id' => $location->id,
        ])->view('layouts.sidebar')->assertDontSee('href="http://localhost/vehicles"', false);

        $this->actingAs($user)->withSession([
            'active_division_id' => $division->id,
            'active_location_id' => $location->id,
        ])->get('/vehicles')->assertForbidden();
        $this->actingAs($user)->withSession([
            'active_division_id' => $division->id,
            'active_location_id' => $location->id,
        ])->get("/vehicles/{$vehicle->id}/details")->assertForbidden();

        $permissions->update($manager, [
            ...$scope,
            'profile' => 'supervisor',
            'permissions' => ['vehicles.view' => '1'],
        ]);

        $this->assertDatabaseMissing('profile_permission_overrides', [
            'tenant_id' => $user->tenant_id,
            'permission_key' => 'vehicles.view',
        ]);
        $this->assertTrue($permissions->allows($user, 'vehicles.view', $scope));
    }

    public function test_location_override_takes_precedence_over_division_override_and_manager_override_is_honored(): void
    {
        [$user, $manager, $division, $location] = $this->supervisorContext();
        $scope = ['division_id' => $division->id, 'location_id' => $location->id, 'module' => 'fleet'];

        ProfilePermissionOverride::create([
            'tenant_id' => $user->tenant_id, 'division_id' => $division->id, 'location_id' => null,
            'module' => 'fleet', 'profile' => 'supervisor', 'permission_key' => 'vehicles.view', 'allowed' => false,
        ]);
        $permissions = app(ProfilePermissionService::class);
        $this->assertFalse($permissions->allows($user, 'vehicles.view', $scope));

        ProfilePermissionOverride::create([
            'tenant_id' => $user->tenant_id, ...$scope,
            'profile' => 'supervisor', 'permission_key' => 'vehicles.view', 'allowed' => true,
        ]);
        $this->assertTrue($permissions->allows($user, 'vehicles.view', $scope));

        ProfilePermissionOverride::create([
            'tenant_id' => $manager->tenant_id, ...$scope,
            'profile' => 'manager', 'permission_key' => 'vehicles.view', 'allowed' => false,
        ]);
        $this->assertFalse($permissions->allows($manager, 'vehicles.view', $scope));
    }

    public function test_vehicle_create_route_requires_its_own_permission(): void
    {
        [$user, , $division, $location] = $this->supervisorContext();
        ProfilePermissionOverride::create([
            'tenant_id' => $user->tenant_id, 'division_id' => $division->id, 'location_id' => $location->id,
            'module' => 'fleet', 'profile' => 'supervisor', 'permission_key' => 'vehicles.create', 'allowed' => false,
        ]);

        $this->actingAs($user)->withSession([
            'active_division_id' => $division->id,
            'active_location_id' => $location->id,
        ])->get('/vehicles/create')->assertForbidden();
    }

    public function test_permissions_tab_is_hidden_when_configuration_permission_is_denied(): void
    {
        [$user, , $division, $location] = $this->supervisorContext();
        ProfilePermissionOverride::create([
            'tenant_id' => $user->tenant_id, 'division_id' => $division->id, 'location_id' => $location->id,
            'module' => 'fleet', 'profile' => 'supervisor', 'permission_key' => 'admin.permissions.configure', 'allowed' => false,
        ]);

        $this->actingAs($user)->withSession([
            'active_division_id' => $division->id,
            'active_location_id' => $location->id,
        ])->view('settings.index', ['tab' => 'general', 'fiscalRequirements' => []])
            ->assertDontSee('>Permissões<', false)
            ->assertDontSee('Abrir permissões');
    }

    public function test_workshop_navigation_is_flat_when_the_visible_set_has_at_most_five_items(): void
    {
        [$user, , $division, $location] = $this->supervisorContext();

        $this->actingAs($user)->withSession([
            'active_division_id' => $division->id,
            'active_location_id' => $location->id,
        ])->view('layouts.sidebar')
            ->assertSee('>Oficina<', false)
            ->assertSee('>Controle de pneus<', false)
            ->assertSee('>Estoque<', false)
            ->assertSee('>Procedimentos<', false)
            ->assertDontSee('id="sidebarWorkshopButton"', false)
            ->assertDontSee('>Visão geral<', false);
    }

    public function test_workshop_flat_navigation_keeps_each_permission_filter(): void
    {
        [$user, , $division, $location] = $this->supervisorContext();
        ProfilePermissionOverride::create([
            'tenant_id' => $user->tenant_id,
            'division_id' => $division->id,
            'location_id' => $location->id,
            'module' => 'fleet',
            'profile' => 'supervisor',
            'permission_key' => 'navigation.stock',
            'allowed' => false,
        ]);

        $this->actingAs($user)->withSession([
            'active_division_id' => $division->id,
            'active_location_id' => $location->id,
        ])->view('layouts.sidebar')
            ->assertDontSee('>Estoque<', false)
            ->assertSee('>Oficina<', false)
            ->assertDontSee('id="sidebarWorkshopButton"', false);
    }

    public function test_workshop_sidebar_keeps_the_dropdown_fallback_rule_for_a_larger_collection(): void
    {
        $sidebar = file_get_contents(resource_path('views/layouts/sidebar.blade.php'));

        $this->assertStringContainsString('$sidebarWorkshopItems->count() <= 5', $sidebar);
        $this->assertStringContainsString('@else', $sidebar);
        $this->assertStringContainsString('id="sidebarWorkshopButton"', $sidebar);
    }

    private function supervisorContext(): array
    {
        $tenant = Tenant::create(['name' => 'Tenant de teste']);
        $division = Division::create(['tenant_id' => $tenant->id, 'name' => 'Divisão de teste']);
        $location = Location::create(['tenant_id' => $tenant->id, 'division_id' => $division->id, 'name' => 'Unidade de teste']);
        User::factory()->create(['tenant_id' => $tenant->id]); // id=1 has an intentional system bypass.
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        UserDivisionAccess::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'division_id' => $division->id,
            'location_id' => $location->id,
            'module' => 'fleet',
            'profile' => 'supervisor',
            'active' => true,
        ]);

        $manager = User::factory()->create(['tenant_id' => $tenant->id]);
        UserDivisionAccess::create([
            'tenant_id' => $tenant->id,
            'user_id' => $manager->id,
            'division_id' => $division->id,
            'location_id' => $location->id,
            'module' => 'fleet',
            'profile' => 'manager',
            'active' => true,
        ]);

        $vehicle = Vehicle::create([
            'tenant_id' => $tenant->id,
            'division_id' => $division->id,
            'location_id' => $location->id,
            'name' => 'Veículo de teste',
            'plate' => 'TES-1234',
            'type' => 'truck',
        ]);

        return [$user, $manager, $division, $location, $vehicle];
    }
}
