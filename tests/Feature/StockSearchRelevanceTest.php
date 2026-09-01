<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\Location;
use App\Models\StockCategory;
use App\Models\StockItem;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserDivisionAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockSearchRelevanceTest extends TestCase
{
    use RefreshDatabase;

    private Location $location;

    protected function setUp(): void
    {
        parent::setUp();

        $tenant = Tenant::create(['name' => 'Tenant da busca']);
        $division = Division::create(['tenant_id' => $tenant->id, 'name' => 'Divisão']);
        $this->location = Location::create(['tenant_id' => $tenant->id, 'division_id' => $division->id, 'name' => 'Unidade', 'active' => true]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        UserDivisionAccess::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'division_id' => $division->id, 'location_id' => $this->location->id, 'module' => 'fleet', 'profile' => 'manager', 'active' => true]);
        $this->actingAs($user)->withSession(['active_division_id' => $division->id, 'active_location_id' => $this->location->id]);
    }

    public function test_search_prioritizes_item_name_and_normalizes_accents_and_multiple_words(): void
    {
        $oils = $this->category('Óleos');
        $batteries = $this->category('Baterias');
        $hydraulics = $this->category('Hidráulica');
        $this->item($oils, 'Óleo hidráulico 68');
        $this->item($oils, 'Lubrificante de transmissão');
        $this->item($batteries, 'Bateria 150 Ah');
        $this->item($hydraulics, 'Mangueira padrão');

        foreach (['68', 'oleo', 'óleo', 'oleo 68'] as $term) {
            $response = $this->get(route('stock.index', ['search' => $term]))->assertOk();
            $categories = $response->viewData('categories');
            $this->assertSame('Óleo hidráulico 68', $categories->flatMap->items->first()->name);
        }

        $hydraulicSearch = $this->get(route('stock.index', ['search' => 'hidraulico']))->viewData('categories');
        $this->assertSame('Óleo hidráulico 68', $hydraulicSearch->flatMap->items->first()->name);
    }

    public function test_search_finds_brand_but_does_not_rank_it_above_item_name(): void
    {
        $category = $this->category('Filtros');
        $this->item($category, 'Filtro de ar', 'Bosch');
        $this->item($category, 'Bosch profissional', 'Outra');

        $response = $this->get(route('stock.index', ['search' => 'bosch']))->assertOk();
        $content = $response->getContent();
        $this->assertLessThan(strpos($content, 'Filtro de ar'), strpos($content, 'Bosch profissional'));
    }

    public function test_category_search_and_empty_search_keep_expected_results(): void
    {
        $batteries = $this->category('Baterias');
        $this->item($batteries, 'Bateria 150 Ah');
        $this->item($this->category('Óleos'), 'Óleo hidráulico 68');

        $this->get(route('stock.index', ['search' => 'baterias']))->assertOk()->assertSee('Baterias')->assertSee('Bateria 150 Ah');
        $this->get(route('stock.index'))->assertOk()->assertSeeInOrder(['Baterias', 'Óleos']);
    }

    private function category(string $name): StockCategory
    {
        return StockCategory::create(['tenant_id' => auth()->user()->tenant_id, 'name' => $name]);
    }

    private function item(StockCategory $category, string $name, ?string $brand = null): void
    {
        StockItem::create(['tenant_id' => auth()->user()->tenant_id, 'location_id' => $this->location->id, 'stock_category_id' => $category->id, 'name' => $name, 'brand' => $brand, 'unit' => 'UN']);
    }
}
