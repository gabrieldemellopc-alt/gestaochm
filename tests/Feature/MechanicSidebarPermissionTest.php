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

class MechanicSidebarPermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_mechanic_sidebar_and_fuel_access_follow_effective_navigation_permissions(): void
    {
        [$user, $division, $location] = $this->mechanicContext();
        $scope = [
            'tenant_id' => $user->tenant_id,
            'division_id' => $division->id,
            'location_id' => $location->id,
            'module' => 'fleet',
            'profile' => 'mechanic',
        ];

        foreach (['navigation.dashboard', 'navigation.vehicles', 'vehicles.view'] as $permissionKey) {
            ProfilePermissionOverride::create([
                ...$scope,
                'permission_key' => $permissionKey,
                'allowed' => true,
            ]);
        }

        $session = [
            'active_division_id' => $division->id,
            'active_location_id' => $location->id,
        ];

        $this->actingAs($user)->withSession($session)
            ->view('layouts.sidebar')
            ->assertSeeText('Dashboard')
            ->assertSeeText('Veículos')
            ->assertSeeText('Abastecimentos')
            ->assertDontSeeText('Oficina')
            ->assertDontSeeText('Estoque')
            ->assertDontSeeText('Pneus');

        $this->actingAs($user)->withSession($session)
            ->get(route('fuel.tanks.index'))
            ->assertOk();

        $this->actingAs($user)->withSession($session)
            ->get(route('vehicles.index'))
            ->assertOk();

        $vehicle = Vehicle::create([
            'tenant_id' => $user->tenant_id,
            'division_id' => $division->id,
            'location_id' => $location->id,
            'name' => 'Veículo do operador',
            'plate' => 'OPR-1234',
            'type' => 'truck',
        ]);

        $dashboard = $this->actingAs($user)->withSession($session)
            ->get(route('dashboard'))
            ->assertOk();

        $dashboard
            ->assertSee('x-show="vehicleActions.panel"', false)
            ->assertSee('x-show="vehicleActions.fuel"', false)
            ->assertSee('x-show="vehicleActions.maintenance"', false)
            ->assertSee('x-show="vehicleActions.tires"', false)
            ->assertSee('x-show="vehicleActions.edit"', false)
            ->assertSee('dashboardFleet({"maintenance":false', false);

        $permissions = app(ProfilePermissionService::class);
        $this->assertTrue($permissions->allows($user, 'fuel.fill_internal'));
        $this->assertFalse($permissions->allows($user, 'maintenance.view'));
        $this->assertFalse($permissions->allows($user, 'tires.view'));
        $this->assertFalse($permissions->allows($user, 'vehicles.update'));

        $this->actingAs($user)->withSession($session)
            ->get(route('vehicles.edit', $vehicle))
            ->assertForbidden();

        ProfilePermissionOverride::create([
            ...$scope,
            'permission_key' => 'navigation.tires',
            'allowed' => true,
        ]);
        ProfilePermissionOverride::create([
            ...$scope,
            'permission_key' => 'tires.view',
            'allowed' => true,
        ]);
        $this->assertTrue($permissions->allows($user, 'tires.view'));
        $this->actingAs($user)->withSession($session)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('dashboardFleet({"maintenance":false,"history":true,"panel":true,"tires":true,"fuel":true,"edit":false})', false);

        $this->actingAs($user)->withSession($session)
            ->get(route('reports.index'))
            ->assertOk();

        ProfilePermissionOverride::create([
            ...$scope,
            'permission_key' => 'navigation.stock',
            'allowed' => true,
        ]);

        $this->actingAs($user)->withSession($session)
            ->view('layouts.sidebar')
            ->assertSeeText('Estoque');

        ProfilePermissionOverride::updateOrCreate(
            [...$scope, 'permission_key' => 'navigation.vehicles'],
            ['allowed' => false]
        );
        ProfilePermissionOverride::updateOrCreate(
            [...$scope, 'permission_key' => 'navigation.fuel'],
            ['allowed' => false]
        );

        $this->actingAs($user)->withSession($session)
            ->view('layouts.sidebar')
            ->assertDontSeeText('Veículos')
            ->assertDontSeeText('Abastecimentos');

        $this->actingAs($user)->withSession($session)
            ->get(route('fuel.tanks.index'))
            ->assertForbidden();

        $this->actingAs($user)->withSession($session)
            ->get(route('vehicles.index'))
            ->assertForbidden();
    }

    public function test_supervisor_dashboard_actions_follow_the_same_effective_permission_map(): void
    {
        [$user, $division, $location] = $this->mechanicContext('supervisor');
        $scope = [
            'tenant_id' => $user->tenant_id,
            'division_id' => $division->id,
            'location_id' => $location->id,
            'module' => 'fleet',
            'profile' => 'supervisor',
        ];

        foreach ([
            'navigation.dashboard', 'navigation.vehicles', 'navigation.workshop',
            'maintenance.view', 'navigation.tires', 'tires.view', 'navigation.fuel',
            'fuel.fill_internal', 'vehicles.update',
        ] as $permissionKey) {
            ProfilePermissionOverride::updateOrCreate(
                [...$scope, 'permission_key' => $permissionKey],
                ['allowed' => true],
            );
        }

        $this->actingAs($user)->withSession([
            'active_division_id' => $division->id,
            'active_location_id' => $location->id,
        ])->get(route('dashboard'))
            ->assertOk()
            ->assertSee('dashboardFleet({"maintenance":true,"history":true,"panel":true,"tires":true,"fuel":true,"edit":true})', false);
    }

    public function test_dashboard_permission_shows_vehicle_information_without_vehicle_module_permission(): void
    {
        [$user, $division, $location] = $this->mechanicContext();
        $scope = [
            'tenant_id' => $user->tenant_id,
            'division_id' => $division->id,
            'location_id' => $location->id,
            'module' => 'fleet',
            'profile' => 'mechanic',
        ];

        foreach (['navigation.dashboard', 'navigation.fuel', 'fuel.fill_internal'] as $permissionKey) {
            ProfilePermissionOverride::create([
                ...$scope,
                'permission_key' => $permissionKey,
                'allowed' => true,
            ]);
        }

        Vehicle::create([
            'tenant_id' => $user->tenant_id,
            'division_id' => $division->id,
            'location_id' => $location->id,
            'name' => 'Veículo visível somente no dashboard',
            'plate' => 'DSH-1234',
            'type' => 'truck',
        ]);

        $session = [
            'active_division_id' => $division->id,
            'active_location_id' => $location->id,
        ];

        $this->actingAs($user)->withSession($session)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSeeText('Veículo visível somente no dashboard')
            ->assertSee('class="dashboard-filter-bar"', false)
            ->assertSeeText('Internos')
            ->assertSeeText('Agregados')
            ->assertSeeText('Alugados')
            ->assertSee("x-data='dashboardFleet({\"maintenance\":false,\"history\":false,\"panel\":false,\"tires\":false,\"fuel\":true,\"edit\":false})'", false);

        ProfilePermissionOverride::updateOrCreate(
            [...$scope, 'permission_key' => 'navigation.dashboard'],
            ['allowed' => false],
        );

        $this->actingAs($user)->withSession($session)
            ->get(route('dashboard'))
            ->assertForbidden();
    }

    public function test_dashboard_fuel_shortcuts_share_the_official_get_route(): void
    {
        [$user, $division, $location] = $this->mechanicContext();
        $scope = [
            'tenant_id' => $user->tenant_id,
            'division_id' => $division->id,
            'location_id' => $location->id,
            'module' => 'fleet',
            'profile' => 'mechanic',
        ];

        foreach (['navigation.dashboard', 'navigation.fuel', 'fuel.fill_internal'] as $permissionKey) {
            ProfilePermissionOverride::create([
                ...$scope,
                'permission_key' => $permissionKey,
                'allowed' => true,
            ]);
        }

        $vehicle = Vehicle::create([
            'tenant_id' => $user->tenant_id,
            'division_id' => $division->id,
            'location_id' => $location->id,
            'name' => 'Veículo do atalho de abastecimento',
            'plate' => 'FUE-1234',
            'type' => 'truck',
        ]);

        $expectedUrl = route('fuel.tanks.index', [
            'fuel_modal' => 'filling',
            'fuel_vehicle_id' => $vehicle->id,
            'return_to' => 'fleet_dashboard',
        ]);

        $dashboard = $this->actingAs($user)->withSession([
            'active_division_id' => $division->id,
            'active_location_id' => $location->id,
        ])->get(route('dashboard'))
            ->assertOk();

        $content = html_entity_decode($dashboard->getContent());

        $this->assertStringContainsString('href="'.$expectedUrl.'"', $content);
        $this->assertStringContainsString(':href="fuelFillingUrl(vehicle.id)"', $content);
        $this->assertStringContainsString("'fuel_modal' => 'filling'", file_get_contents(resource_path('views/dashboard.blade.php')));
        $this->assertStringContainsString("'return_to' => 'fleet_dashboard'", file_get_contents(resource_path('views/dashboard.blade.php')));
        $this->assertStringContainsString('fuelFillingUrl(vehicleId)', $content);
        $this->assertStringNotContainsString('/fuel/tanks?fuel_modal=filling', $content);
    }

    private function mechanicContext(string $profile = 'mechanic'): array
    {
        $tenant = Tenant::create(['name' => 'Tenant sidebar']);
        $division = Division::create(['tenant_id' => $tenant->id, 'name' => 'AKSA']);
        $location = Location::create(['tenant_id' => $tenant->id, 'division_id' => $division->id, 'name' => 'Imperatriz']);
        User::factory()->create(['tenant_id' => $tenant->id]); // Mantém o bypass de id 1 fora do cenário.
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        UserDivisionAccess::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'division_id' => $division->id,
            'location_id' => $location->id,
            'module' => 'fleet',
            'profile' => $profile,
            'active' => true,
        ]);

        return [$user, $division, $location];
    }
}
