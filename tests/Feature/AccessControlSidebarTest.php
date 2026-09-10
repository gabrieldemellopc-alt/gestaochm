<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\Location;
use App\Models\ProfilePermissionOverride;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserDivisionAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessControlSidebarTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_admin_sees_an_active_manage_access_link_on_the_access_control_page(): void
    {
        [$admin, $division, $location] = $this->adminContext();

        $response = $this->actingAs($admin)
            ->withSession([
                'active_division_id' => $division->id,
                'active_location_id' => $location->id,
            ])
            ->get(route('access-control.index'));

        $response->assertOk()
            ->assertSee('Gerenciar acessos');

        $this->assertMatchesRegularExpression(
            '/href="[^"]*\/access-control"[^>]*class="sidebar-link active"/',
            $response->getContent()
        );
    }

    public function test_user_without_access_management_permission_does_not_see_the_link_and_is_blocked_by_the_route(): void
    {
        [$admin, $division, $location] = $this->adminContext();
        ProfilePermissionOverride::create([
            'tenant_id' => $admin->tenant_id,
            'division_id' => $division->id,
            'location_id' => $location->id,
            'module' => 'fleet',
            'profile' => 'admin',
            'permission_key' => 'admin.access.manage',
            'allowed' => false,
        ]);

        $this->actingAs($admin)
            ->withSession([
                'active_division_id' => $division->id,
                'active_location_id' => $location->id,
            ])
            ->view('layouts.sidebar')
            ->assertDontSee('Gerenciar acessos');

        $this->actingAs($admin)
            ->withSession([
                'active_division_id' => $division->id,
                'active_location_id' => $location->id,
            ])
            ->get(route('access-control.index'))
            ->assertForbidden();
    }

    private function adminContext(): array
    {
        $tenant = Tenant::create(['name' => 'Tenant de acessos']);
        $division = Division::create(['tenant_id' => $tenant->id, 'name' => 'Divisão']);
        $location = Location::create(['tenant_id' => $tenant->id, 'division_id' => $division->id, 'name' => 'Unidade']);
        User::factory()->create(['tenant_id' => $tenant->id]);
        $admin = User::factory()->create(['tenant_id' => $tenant->id]);

        UserDivisionAccess::create([
            'tenant_id' => $tenant->id,
            'user_id' => $admin->id,
            'division_id' => $division->id,
            'location_id' => $location->id,
            'module' => 'fleet',
            'profile' => 'admin',
            'active' => true,
        ]);

        return [$admin, $division, $location];
    }
}
