<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\Location;
use App\Models\ProfilePermissionOverride;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserDivisionAccess;
use App\Services\Permissions\ProfilePermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PermissionMechanicProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_mechanic_is_configurable_with_friendly_label_and_conservative_fuel_defaults(): void
    {
        [$admin, $mechanic, $division, $location] = $this->context();
        $scope = $this->scope($division, $location);

        $this->actingAs($admin)->withSession($this->activeScopeSession($division, $location));

        $matrix = app(ProfilePermissionService::class)->matrix($admin, $scope);
        $permissions = collect($matrix['groups'])
            ->flatMap(fn (array $group) => $group['permissions'])
            ->keyBy('key');

        $this->assertSame('Operador Combustível', $matrix['profiles']['mechanic']);
        $this->assertContains('mechanic', app(ProfilePermissionService::class)->managedProfiles());
        $this->assertSame('mechanic', $matrix['scope']['profile']);
        $this->assertTrue($permissions['navigation.fuel']['allowed']);
        $this->assertTrue($permissions['fuel.view']['allowed']);
        $this->assertTrue($permissions['fuel.receive']['allowed']);
        $this->assertTrue($permissions['fuel.fill_internal']['allowed']);
        $this->assertTrue($permissions['fuel.fill_external']['allowed']);
        $this->assertTrue($permissions['reports.fuel']['allowed']);
        $this->assertFalse($permissions['admin.access.manage']['allowed']);
        $this->assertFalse($permissions['vehicles.view']['allowed']);

        $supervisorMatrix = app(ProfilePermissionService::class)->matrix($admin, [
            ...$scope,
            'profile' => 'supervisor',
        ]);
        $supervisorPermissions = collect($supervisorMatrix['groups'])
            ->flatMap(fn (array $group) => $group['permissions'])
            ->keyBy('key');
        $this->assertSame('Supervisor', $supervisorMatrix['profiles']['supervisor']);
        $this->assertFalse($supervisorPermissions['reports.fuel']['allowed']);

        $this->actingAs($admin)->withSession($this->activeScopeSession($division, $location))
            ->get(route('permissions.index', $scope))
            ->assertOk()
            ->assertSee('Operador Combustível')
            ->assertSee('value="mechanic"', false);

        $this->assertTrue(app(ProfilePermissionService::class)->allows($mechanic, 'fuel.view', $scope));
        $this->assertFalse(app(ProfilePermissionService::class)->allows($mechanic, 'vehicles.view', $scope));
    }

    public function test_admin_can_save_reset_apply_and_copy_mechanic_overrides_without_changing_the_profile_key(): void
    {
        [$admin, $mechanic, $division, $location, $secondLocation] = $this->context();
        $scope = $this->scope($division, $location);
        $session = $this->activeScopeSession($division, $location);
        $service = app(ProfilePermissionService::class);

        $this->actingAs($admin)->withSession($session)
            ->patch(route('permissions.update'), [
                ...$scope,
                'permissions' => [
                    'navigation.fuel' => '1',
                    'fuel.view' => '1',
                    'vehicles.view' => '1',
                ],
            ])
            ->assertRedirectContains('profile=mechanic');

        $this->assertDatabaseHas('profile_permission_overrides', [
            'tenant_id' => $admin->tenant_id,
            'division_id' => $division->id,
            'location_id' => $location->id,
            'module' => 'fleet',
            'profile' => 'mechanic',
            'permission_key' => 'vehicles.view',
            'allowed' => true,
        ]);
        $this->assertTrue($service->allows($mechanic, 'vehicles.view', $scope));

        $this->actingAs($admin)->withSession($session)
            ->post(route('permissions.apply-to-division'), $scope)
            ->assertRedirectContains('profile=mechanic');

        $this->assertDatabaseHas('profile_permission_overrides', [
            'tenant_id' => $admin->tenant_id,
            'division_id' => $division->id,
            'location_id' => $secondLocation->id,
            'module' => 'fleet',
            'profile' => 'mechanic',
            'permission_key' => 'vehicles.view',
            'allowed' => true,
        ]);

        ProfilePermissionOverride::where([
            'tenant_id' => $admin->tenant_id,
            'division_id' => $division->id,
            'location_id' => $location->id,
            'module' => 'fleet',
            'profile' => 'mechanic',
            'permission_key' => 'vehicles.view',
        ])->delete();

        $this->actingAs($admin)->withSession($session)
            ->post(route('permissions.copy-from-location'), [
                ...$scope,
                'source_location_id' => $secondLocation->id,
            ])
            ->assertRedirectContains('profile=mechanic');

        $this->assertTrue($service->allows($mechanic, 'vehicles.view', $scope));

        $this->actingAs($admin)->withSession($session)
            ->post(route('permissions.reset'), $scope)
            ->assertRedirectContains('profile=mechanic');

        $this->assertFalse($service->allows($mechanic, 'vehicles.view', $scope));
        $this->assertDatabaseMissing('profile_permission_overrides', [
            'tenant_id' => $admin->tenant_id,
            'division_id' => $division->id,
            'location_id' => $location->id,
            'module' => 'fleet',
            'profile' => 'mechanic',
        ]);
    }

    public function test_mechanic_scope_cannot_be_resolved_for_another_tenant(): void
    {
        [$admin, , $division, $location] = $this->context();
        $otherTenant = Tenant::create(['name' => 'Outro tenant']);
        $otherDivision = Division::create(['tenant_id' => $otherTenant->id, 'name' => 'Outra divisão']);
        $otherLocation = Location::create([
            'tenant_id' => $otherTenant->id,
            'division_id' => $otherDivision->id,
            'name' => 'Outra unidade',
        ]);

        $this->actingAs($admin)->withSession($this->activeScopeSession($division, $location));

        try {
            app(ProfilePermissionService::class)->matrix($admin, [
                'division_id' => $otherDivision->id,
                'location_id' => $otherLocation->id,
                'module' => 'fleet',
                'profile' => 'mechanic',
            ]);

            $this->fail('O escopo de outro tenant não pode ser configurado.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('division_id', $exception->errors());
        }
    }

    private function context(): array
    {
        $tenant = Tenant::create(['name' => 'Tenant de permissões']);
        $division = Division::create(['tenant_id' => $tenant->id, 'name' => 'AKSA']);
        $location = Location::create(['tenant_id' => $tenant->id, 'division_id' => $division->id, 'name' => 'Imperatriz']);
        $secondLocation = Location::create(['tenant_id' => $tenant->id, 'division_id' => $division->id, 'name' => 'Barreiras']);
        User::factory()->create(['tenant_id' => $tenant->id]); // Preserva o bypass intencional do usuário 1 fora do teste.
        $admin = User::factory()->create(['tenant_id' => $tenant->id]);
        $mechanic = User::factory()->create(['tenant_id' => $tenant->id]);

        UserDivisionAccess::create([
            'tenant_id' => $tenant->id,
            'user_id' => $admin->id,
            'division_id' => $division->id,
            'location_id' => null,
            'module' => 'fleet',
            'profile' => 'admin',
            'active' => true,
        ]);

        UserDivisionAccess::create([
            'tenant_id' => $tenant->id,
            'user_id' => $mechanic->id,
            'division_id' => $division->id,
            'location_id' => $location->id,
            'module' => 'fleet',
            'profile' => 'mechanic',
            'active' => true,
        ]);

        return [$admin, $mechanic, $division, $location, $secondLocation];
    }

    private function scope(Division $division, Location $location): array
    {
        return [
            'division_id' => $division->id,
            'location_id' => $location->id,
            'module' => 'fleet',
            'profile' => 'mechanic',
        ];
    }

    private function activeScopeSession(Division $division, Location $location): array
    {
        return [
            'active_division_id' => $division->id,
            'active_location_id' => $location->id,
        ];
    }
}
