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

    public function test_workshop_consumable_is_persisted_as_true_or_false_on_create_and_update(): void
    {
        $category = StockCategory::create(['tenant_id' => $this->context['tenant']->id, 'name' => 'Oficina']);
        $payload = ['stock_category_id' => $category->id, 'name' => 'Graxa', 'unit' => 'KG', 'minimum_quantity' => 2];

        $this->post(route('stock.items.store'), $payload + ['is_workshop_consumable' => '1'])->assertRedirect();
        $enabled = StockItem::where('name', 'Graxa')->firstOrFail();
        $this->assertTrue($enabled->is_workshop_consumable);
        $this->assertSame(0.0, (float) $enabled->quantity);
        $this->assertSame(0.0, (float) $enabled->unit_cost);

        $this->post(route('stock.items.store'), array_merge($payload, ['name' => 'Filtro']))->assertRedirect();
        $disabled = StockItem::where('name', 'Filtro')->firstOrFail();
        $this->assertFalse($disabled->is_workshop_consumable);

        $this->put(route('stock.items.update', $disabled), ['name' => 'Filtro', 'unit' => 'UNID', 'minimum_quantity' => 2, 'is_workshop_consumable' => '1'])->assertRedirect();
        $this->assertTrue($disabled->fresh()->is_workshop_consumable);

        $this->put(route('stock.items.update', $disabled), ['name' => 'Filtro', 'unit' => 'UNID', 'minimum_quantity' => 2])->assertRedirect();
        $this->assertFalse($disabled->fresh()->is_workshop_consumable);
        $this->assertSame(0.0, (float) $disabled->fresh()->quantity);
        $this->assertSame(0.0, (float) $disabled->fresh()->unit_cost);
    }

    public function test_item_category_can_be_changed_within_tenant_without_rewriting_movements_and_is_audited(): void
    {
        $old = StockCategory::create(['tenant_id' => $this->context['tenant']->id, 'name' => 'Filtros']);
        $new = StockCategory::create(['tenant_id' => $this->context['tenant']->id, 'name' => 'Lubrificantes']);
        $item = StockItem::create(['tenant_id' => $this->context['tenant']->id, 'location_id' => $this->context['location']->id, 'stock_category_id' => $old->id, 'name' => 'Filtro', 'unit' => 'UNID', 'minimum_quantity' => 1]);
        $movement = \App\Models\StockMovement::create(['tenant_id' => $this->context['tenant']->id, 'location_id' => $this->context['location']->id, 'stock_item_id' => $item->id, 'movement_type' => 'in', 'quantity' => 2, 'unit_cost' => 10, 'total_cost' => 20, 'description' => 'Entrada existente']);

        $this->get(route('stock.items.data', $item))->assertOk()->assertJsonPath('category.id', $old->id);
        $this->put(route('stock.items.update', $item), ['stock_category_id' => $new->id, 'name' => 'Filtro', 'unit' => 'UNID', 'minimum_quantity' => 1])->assertRedirect();

        $this->assertSame($new->id, $item->fresh()->stock_category_id);
        $this->assertDatabaseHas('stock_movements', ['id' => $movement->id, 'stock_item_id' => $item->id, 'description' => 'Entrada existente']);
        $audit = SystemAuditLog::query()->where('auditable_type', StockItem::class)->where('auditable_id', $item->id)->where('action', 'updated')->latest('id')->firstOrFail();
        $this->assertSame('Filtros', $audit->before_data['category']['name']);
        $this->assertSame('Lubrificantes', $audit->after_data['category']['name']);
    }

    public function test_item_update_rejects_category_from_another_tenant(): void
    {
        $local = StockCategory::create(['tenant_id' => $this->context['tenant']->id, 'name' => 'Local']);
        $foreignTenant = Tenant::create(['name' => 'Outro tenant']);
        $foreign = StockCategory::create(['tenant_id' => $foreignTenant->id, 'name' => 'Estrangeira']);
        $item = StockItem::create(['tenant_id' => $this->context['tenant']->id, 'location_id' => $this->context['location']->id, 'stock_category_id' => $local->id, 'name' => 'Item', 'unit' => 'UNID', 'minimum_quantity' => 0]);

        $this->put(route('stock.items.update', $item), ['stock_category_id' => $foreign->id, 'name' => 'Item', 'unit' => 'UNID', 'minimum_quantity' => 0])->assertSessionHasErrors('stock_category_id');
        $this->assertSame($local->id, $item->fresh()->stock_category_id);
    }

    public function test_user_without_item_management_permission_cannot_change_category(): void
    {
        $category = StockCategory::create(['tenant_id' => $this->context['tenant']->id, 'name' => 'Filtros']);
        $replacement = StockCategory::create(['tenant_id' => $this->context['tenant']->id, 'name' => 'Óleos']);
        $item = StockItem::create(['tenant_id' => $this->context['tenant']->id, 'location_id' => $this->context['location']->id, 'stock_category_id' => $category->id, 'name' => 'Item', 'unit' => 'UNID', 'minimum_quantity' => 0]);
        $user = User::factory()->create(['tenant_id' => $this->context['tenant']->id]);
        UserDivisionAccess::create(['tenant_id' => $this->context['tenant']->id, 'user_id' => $user->id, 'division_id' => $this->context['division']->id, 'location_id' => $this->context['location']->id, 'module' => 'fleet', 'profile' => 'supervisor', 'active' => true]);

        $this->actingAs($user)->withSession(['active_division_id' => $this->context['division']->id, 'active_location_id' => $this->context['location']->id])
            ->put(route('stock.items.update', $item), ['stock_category_id' => $replacement->id, 'name' => 'Item', 'unit' => 'UNID', 'minimum_quantity' => 0])
            ->assertForbidden();
        $this->assertSame($category->id, $item->fresh()->stock_category_id);
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
