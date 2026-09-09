<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\Location;
use App\Models\ProfilePermissionOverride;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserDivisionAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierPermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_supervisor_can_see_the_supplier_menu_and_search_by_name_or_cnpj_without_management_permissions(): void
    {
        [$user, $tenant, $division, $location] = $this->supervisorContext();
        $supplier = Supplier::create(['tenant_id' => $tenant->id, 'trade_name' => 'Fornecedor Central', 'document' => '40187670000183', 'document_type' => 'cnpj', 'normalized_name' => 'fornecedor central', 'active' => true]);
        Supplier::create(['tenant_id' => Tenant::create(['name' => 'Outro tenant'])->id, 'trade_name' => 'Fornecedor Externo', 'document' => '11222333000181', 'document_type' => 'cnpj', 'normalized_name' => 'fornecedor externo', 'active' => true]);

        $response = $this->actingAs($user)->withSession($this->activeScopeSession($division, $location));
        $response->view('layouts.topbar')->assertSee('Fornecedores (CNPJ)');
        $this->get(route('suppliers.index'))->assertOk()->assertSee('Fornecedor Central');
        $this->getJson(route('suppliers.search', ['q' => 'Central']))->assertOk()->assertJsonPath('0.id', $supplier->id)->assertJsonMissing(['name' => 'Fornecedor Externo']);
        $this->getJson(route('suppliers.search', ['document' => '40.187.670/0001-83']))->assertOk()->assertJsonPath('0.id', $supplier->id);

        $this->post(route('suppliers.store'), ['trade_name' => 'Sem permissão'])->assertForbidden();
        $this->put(route('suppliers.update', $supplier), ['trade_name' => 'Alteração negada'])->assertForbidden();
        $this->patch(route('suppliers.status', $supplier), ['active' => false])->assertForbidden();
    }

    public function test_supplier_permissions_are_catalogued_and_explicit_false_override_blocks_menu_and_search(): void
    {
        [$user, , $division, $location] = $this->supervisorContext();
        $scope = ['tenant_id' => $user->tenant_id, 'division_id' => $division->id, 'location_id' => $location->id, 'module' => 'fleet', 'profile' => 'supervisor'];

        $permissions = collect(config('chm_permissions.groups.suppliers.permissions'));
        $this->assertSame('Visualizar fornecedores', $permissions['suppliers.view']['label']);
        $this->assertArrayHasKey('suppliers.create', $permissions->all());
        $this->assertArrayHasKey('suppliers.update', $permissions->all());
        $this->assertArrayHasKey('suppliers.change_status', $permissions->all());
        $this->assertArrayHasKey('suppliers.select', $permissions->all());

        ProfilePermissionOverride::create([...$scope, 'permission_key' => 'suppliers.view', 'allowed' => false]);
        ProfilePermissionOverride::create([...$scope, 'permission_key' => 'suppliers.select', 'allowed' => false]);

        $this->actingAs($user)->withSession($this->activeScopeSession($division, $location))
            ->view('layouts.topbar')->assertDontSee('Fornecedores (CNPJ)');
        $this->get(route('suppliers.index'))->assertForbidden();
        $this->getJson(route('suppliers.search', ['q' => 'Fornecedor']))->assertForbidden();
    }

    public function test_explicit_management_permissions_allow_create_edit_and_status_change(): void
    {
        [$user, $tenant, $division, $location] = $this->supervisorContext();
        $scope = ['tenant_id' => $tenant->id, 'division_id' => $division->id, 'location_id' => $location->id, 'module' => 'fleet', 'profile' => 'supervisor'];
        foreach (['suppliers.create', 'suppliers.update', 'suppliers.change_status'] as $permission) {
            ProfilePermissionOverride::create([...$scope, 'permission_key' => $permission, 'allowed' => true]);
        }

        $this->actingAs($user)->withSession($this->activeScopeSession($division, $location))
            ->post(route('suppliers.store'), ['trade_name' => 'Fornecedor Autorizado', 'document' => '40.187.670/0001-83', 'active' => true])
            ->assertRedirect(route('suppliers.index'));
        $supplier = Supplier::where('tenant_id', $tenant->id)->where('document', '40187670000183')->firstOrFail();

        $this->put(route('suppliers.update', $supplier), ['trade_name' => 'Fornecedor Editado', 'document' => '40.187.670/0001-83', 'active' => true])->assertRedirect();
        $this->patch(route('suppliers.status', $supplier), ['active' => false])->assertRedirect();
        $this->assertDatabaseHas('suppliers', ['id' => $supplier->id, 'trade_name' => 'Fornecedor Editado', 'active' => false]);
    }

    private function supervisorContext(): array
    {
        $tenant = Tenant::create(['name' => 'Tenant fornecedor']);
        $division = Division::create(['tenant_id' => $tenant->id, 'name' => 'Divisão fornecedor']);
        $location = Location::create(['tenant_id' => $tenant->id, 'division_id' => $division->id, 'name' => 'Unidade fornecedor']);
        User::factory()->create(['tenant_id' => $tenant->id]); // Reserva o bypass de sistema (id 1).
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        UserDivisionAccess::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'division_id' => $division->id, 'location_id' => $location->id, 'module' => 'fleet', 'profile' => 'supervisor', 'active' => true]);

        return [$user, $tenant, $division, $location];
    }

    private function activeScopeSession(Division $division, Location $location): array
    {
        return ['active_division_id' => $division->id, 'active_location_id' => $location->id];
    }
}
