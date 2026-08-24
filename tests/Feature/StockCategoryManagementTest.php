<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\Location;
use App\Models\StockCategory;
use App\Models\StockItem;
use App\Models\SystemAuditLog;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserDivisionAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockCategoryManagementTest extends TestCase
{
    use RefreshDatabase;

    private array $context;

    protected function setUp(): void
    {
        parent::setUp();

        $tenant = Tenant::create(['name' => 'Tenant de estoque']);
        $division = Division::create(['tenant_id' => $tenant->id, 'name' => 'Divisão']);
        $location = Location::create(['tenant_id' => $tenant->id, 'division_id' => $division->id, 'name' => 'Unidade', 'active' => true]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        UserDivisionAccess::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'division_id' => $division->id, 'location_id' => $location->id, 'module' => 'fleet', 'profile' => 'manager', 'active' => true]);

        $this->actingAs($user)->withSession(['active_division_id' => $division->id, 'active_location_id' => $location->id]);
        $this->context = compact('tenant', 'division', 'location', 'user');
    }

    public function test_category_can_be_created_updated_and_audited(): void
    {
        $this->post(route('stock.categories.store'), ['name' => ' Filtros '])->assertRedirect();
        $category = StockCategory::firstOrFail();
        $this->assertSame('Filtros', $category->name);

        $this->put(route('stock.categories.update', $category), ['name' => 'Lubrificantes'])->assertRedirect();
        $this->assertSame('Lubrificantes', $category->fresh()->name);
        $this->assertSame(1, SystemAuditLog::where('auditable_type', StockCategory::class)->where('action', 'created')->count());
        $this->assertSame(1, SystemAuditLog::where('auditable_type', StockCategory::class)->where('action', 'updated')->count());
    }

    public function test_category_without_items_is_deleted_and_audited(): void
    {
        $category = StockCategory::create(['tenant_id' => $this->context['tenant']->id, 'name' => 'Sem uso']);

        $this->delete(route('stock.categories.destroy', $category))->assertRedirect();

        $this->assertDatabaseMissing('stock_categories', ['id' => $category->id]);
        $this->assertDatabaseHas('system_audit_logs', ['auditable_type' => StockCategory::class, 'auditable_id' => $category->id, 'action' => 'deleted']);
    }

    public function test_category_with_items_is_not_deleted_or_detached(): void
    {
        $category = StockCategory::create(['tenant_id' => $this->context['tenant']->id, 'name' => 'Em uso']);
        $item = StockItem::create(['tenant_id' => $this->context['tenant']->id, 'location_id' => $this->context['location']->id, 'stock_category_id' => $category->id, 'name' => 'Filtro de ar', 'unit' => 'UN']);

        $this->delete(route('stock.categories.destroy', $category))->assertRedirect()->assertSessionHas('error');

        $this->assertDatabaseHas('stock_categories', ['id' => $category->id]);
        $this->assertDatabaseHas('stock_items', ['id' => $item->id, 'stock_category_id' => $category->id]);
    }

    public function test_tenant_category_is_also_protected_when_used_by_another_location(): void
    {
        $otherLocation = Location::create(['tenant_id' => $this->context['tenant']->id, 'division_id' => $this->context['division']->id, 'name' => 'Outra unidade', 'active' => true]);
        $category = StockCategory::create(['tenant_id' => $this->context['tenant']->id, 'name' => 'Compartilhada']);
        $item = StockItem::create(['tenant_id' => $this->context['tenant']->id, 'location_id' => $otherLocation->id, 'stock_category_id' => $category->id, 'name' => 'Item de outra unidade', 'unit' => 'UN']);

        $this->delete(route('stock.categories.destroy', $category))->assertRedirect()->assertSessionHas('error', fn ($message) => str_contains($message, 'compartilhada'));

        $this->assertDatabaseHas('stock_categories', ['id' => $category->id]);
        $this->assertDatabaseHas('stock_items', ['id' => $item->id, 'stock_category_id' => $category->id]);
    }

    public function test_category_count_only_includes_items_from_the_active_location(): void
    {
        $otherLocation = Location::create(['tenant_id' => $this->context['tenant']->id, 'division_id' => $this->context['division']->id, 'name' => 'Outra unidade', 'active' => true]);
        $category = StockCategory::create(['tenant_id' => $this->context['tenant']->id, 'name' => 'Somente externa']);
        StockItem::create(['tenant_id' => $this->context['tenant']->id, 'location_id' => $otherLocation->id, 'stock_category_id' => $category->id, 'name' => 'Item externo', 'unit' => 'UN']);

        $response = $this->get(route('stock.index'))->assertOk()->assertSee('Somente externa');
        $this->assertMatchesRegularExpression('/Somente externa[\s\S]{0,120}0\s+item\(ns\) cadastrado\(s\)/', $response->getContent());
    }

    public function test_duplicate_name_is_rejected_within_the_tenant_but_not_across_tenants(): void
    {
        StockCategory::create(['tenant_id' => $this->context['tenant']->id, 'name' => 'Filtros']);
        $this->post(route('stock.categories.store'), ['name' => ' filtros '])->assertSessionHasErrors('name');

        $otherTenant = Tenant::create(['name' => 'Outro tenant']);
        $other = StockCategory::create(['tenant_id' => $otherTenant->id, 'name' => 'Filtros']);
        $this->assertNotNull($other);
    }

    public function test_categories_of_another_tenant_and_users_without_permission_are_blocked(): void
    {
        $otherTenant = Tenant::create(['name' => 'Outro tenant']);
        $foreign = StockCategory::create(['tenant_id' => $otherTenant->id, 'name' => 'Estrangeira']);
        $this->put(route('stock.categories.update', $foreign), ['name' => 'Alterada'])->assertForbidden();

        $user = User::factory()->create(['tenant_id' => $this->context['tenant']->id]);
        UserDivisionAccess::create(['tenant_id' => $this->context['tenant']->id, 'user_id' => $user->id, 'division_id' => $this->context['division']->id, 'location_id' => $this->context['location']->id, 'module' => 'fleet', 'profile' => 'supervisor', 'active' => true]);
        $this->actingAs($user)->withSession(['active_division_id' => $this->context['division']->id, 'active_location_id' => $this->context['location']->id]);
        $this->post(route('stock.categories.store'), ['name' => 'Sem permissão'])->assertForbidden();
    }
}
