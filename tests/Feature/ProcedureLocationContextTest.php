<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\Location;
use App\Models\Procedure;
use App\Models\ProcedureField;
use App\Models\StockCategory;
use App\Models\StockItem;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserDivisionAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class ProcedureLocationContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_and_edit_load_stock_options_only_for_the_active_location(): void
    {
        $context = $this->activeContext();
        $category = StockCategory::create(['tenant_id' => $context['tenant']->id, 'name' => 'Filtros']);
        $fallbackCategory = StockCategory::create(['tenant_id' => $context['tenant']->id, 'name' => 'Lubrificantes']);
        $visibleItem = StockItem::create([
            'tenant_id' => $context['tenant']->id,
            'location_id' => $context['location']->id,
            'stock_category_id' => $category->id,
            'name' => 'Filtro da unidade ativa',
            'unit' => 'UN',
            'active' => true,
        ]);
        $outsideItem = StockItem::create([
            'tenant_id' => $context['tenant']->id,
            'location_id' => $context['otherLocation']->id,
            'stock_category_id' => $category->id,
            'name' => 'Filtro de outra unidade',
            'unit' => 'UN',
            'active' => true,
        ]);
        $procedure = Procedure::create([
            'tenant_id' => $context['tenant']->id,
            'location_id' => $context['location']->id,
            'name' => 'Troca de filtros',
            'can_be_internal' => true,
        ]);
        $procedure->stockItems()->attach($visibleItem);
        ProcedureField::create([
            'procedure_id' => $procedure->id,
            'label' => 'Material padrão',
            'slug' => 'material_padrao',
            'field_type' => 'stock_item',
            'stock_category_id' => $fallbackCategory->id,
            'sort_order' => 0,
        ]);

        foreach ([route('procedures.create'), route('procedures.edit', $procedure)] as $route) {
            $this->get($route)
                ->assertOk()
                ->assertViewHas('categories', fn (Collection $categories) => $categories->pluck('id')->sort()->values()->all() === [$category->id, $fallbackCategory->id])
                ->assertViewHas('stockItems', fn (Collection $items) => $items->pluck('id')->all() === [$visibleItem->id]);
        }

        $edit = $this->get(route('procedures.edit', $procedure));
        $edit->assertViewHas('procedure', fn (Procedure $loaded) => $loaded->stockItems->pluck('id')->all() === [$visibleItem->id]
            && $loaded->fields->first()?->stock_category_id === $fallbackCategory->id);
        $edit->assertDontSee($outsideItem->name);
    }

    public function test_create_keeps_the_existing_redirect_when_no_active_location_is_available(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant sem unidade']);
        $division = Division::create(['tenant_id' => $tenant->id, 'name' => 'Divisão']);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($user)
            ->withSession(['active_division_id' => $division->id])
            ->get(route('procedures.create'))
            ->assertRedirect(route('portal'))
            ->assertSessionHas('warning', 'Selecione uma unidade para continuar.');
    }

    private function activeContext(): array
    {
        $tenant = Tenant::create(['name' => 'Tenant de procedimentos']);
        $division = Division::create(['tenant_id' => $tenant->id, 'name' => 'Divisão']);
        $location = Location::create(['tenant_id' => $tenant->id, 'division_id' => $division->id, 'name' => 'Unidade ativa', 'active' => true]);
        $otherLocation = Location::create(['tenant_id' => $tenant->id, 'division_id' => $division->id, 'name' => 'Outra unidade', 'active' => true]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        UserDivisionAccess::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'division_id' => $division->id,
            'location_id' => $location->id,
            'module' => 'fleet',
            'profile' => 'manager',
            'active' => true,
        ]);

        $this->actingAs($user)->withSession([
            'active_division_id' => $division->id,
            'active_location_id' => $location->id,
        ]);

        return compact('tenant', 'division', 'location', 'otherLocation', 'user');
    }
}
