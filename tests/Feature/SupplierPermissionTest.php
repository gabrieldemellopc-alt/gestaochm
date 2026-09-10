<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\Location;
use App\Models\ProfilePermissionOverride;
use App\Models\Supplier;
use App\Models\SupplierAlias;
use App\Models\SystemAuditLog;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserDivisionAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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

    public function test_authorized_merge_keeps_the_first_supplier_transfers_document_and_preserves_old_name_as_alias(): void
    {
        [$user, $tenant, $division, $location] = $this->supervisorContext();
        $scope = ['tenant_id' => $tenant->id, 'division_id' => $division->id, 'location_id' => $location->id, 'module' => 'fleet', 'profile' => 'supervisor'];
        ProfilePermissionOverride::create([...$scope, 'permission_key' => 'suppliers.merge', 'allowed' => true]);
        $primary = Supplier::create(['tenant_id' => $tenant->id, 'trade_name' => 'Principal', 'normalized_name' => 'principal', 'active' => true]);
        $secondary = Supplier::create(['tenant_id' => $tenant->id, 'trade_name' => 'Incorporado', 'document' => '40187670000183', 'document_type' => 'cnpj', 'normalized_name' => 'incorporado', 'active' => true]);
        SupplierAlias::create(['supplier_id' => $primary->id, 'alias' => 'Comum Ltda', 'normalized_alias' => 'comum']);
        SupplierAlias::create(['supplier_id' => $secondary->id, 'alias' => 'COMUM', 'normalized_alias' => 'comum']);
        // Regression coverage: this table intentionally has no tenant_id. Its scope is its maintenance parent.
        $this->assertFalse(Schema::hasColumn('maintenance_record_items', 'tenant_id'));
        // A tenant-owning relation must remain constrained during the bulk transfer.
        DB::table('workshop_expenses')->insert(['tenant_id' => $tenant->id, 'division_id' => $division->id, 'location_id' => $location->id, 'expense_date' => now()->toDateString(), 'category' => 'parts', 'description' => 'Despesa de fornecedor', 'supplier_id' => $secondary->id, 'amount' => 10, 'created_by' => $user->id, 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($user)->withSession($this->activeScopeSession($division, $location))
            ->post(route('suppliers.merge', $primary), ['secondary_supplier_id' => $secondary->id, 'name_source' => 'primary', 'aliases_source' => 'both'])
            ->assertRedirect(route('suppliers.index'));

        $this->assertDatabaseMissing('suppliers', ['id' => $secondary->id]);
        $this->assertDatabaseHas('suppliers', ['id' => $primary->id, 'trade_name' => 'Principal', 'document' => '40187670000183']);
        $this->assertDatabaseHas('supplier_aliases', ['supplier_id' => $primary->id, 'normalized_alias' => 'incorporado']);
        $this->assertSame(1, SupplierAlias::where('supplier_id', $primary->id)->where('normalized_alias', 'comum')->count());
        $this->assertDatabaseHas('workshop_expenses', ['tenant_id' => $tenant->id, 'supplier_id' => $primary->id, 'description' => 'Despesa de fornecedor']);
        $this->assertDatabaseHas('system_audit_logs', ['tenant_id' => $tenant->id, 'action' => 'supplier_merged', 'auditable_id' => $primary->id]);
    }

    public function test_merge_with_different_documents_requires_an_explicit_choice_and_denies_users_without_permission(): void
    {
        [$user, $tenant, $division, $location] = $this->supervisorContext();
        $primary = Supplier::create(['tenant_id' => $tenant->id, 'trade_name' => 'Primeiro', 'document' => '40187670000183', 'document_type' => 'cnpj', 'normalized_name' => 'primeiro', 'active' => true]);
        $secondary = Supplier::create(['tenant_id' => $tenant->id, 'trade_name' => 'Segundo', 'document' => '11222333000181', 'document_type' => 'cnpj', 'normalized_name' => 'segundo', 'active' => true]);
        $client = $this->actingAs($user)->withSession($this->activeScopeSession($division, $location));
        $client->post(route('suppliers.merge', $primary), ['secondary_supplier_id' => $secondary->id, 'name_source' => 'primary', 'aliases_source' => 'both'])->assertForbidden();

        ProfilePermissionOverride::create(['tenant_id' => $tenant->id, 'division_id' => $division->id, 'location_id' => $location->id, 'module' => 'fleet', 'profile' => 'supervisor', 'permission_key' => 'suppliers.merge', 'allowed' => true]);
        $this->post(route('suppliers.merge', $primary), ['secondary_supplier_id' => $secondary->id, 'name_source' => 'primary', 'aliases_source' => 'both'])
            ->assertSessionHasErrors('document_source');
        $this->assertDatabaseHas('suppliers', ['id' => $secondary->id]);
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
