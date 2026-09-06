<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\Location;
use App\Models\StockItem;
use App\Models\StockItemAlias;
use App\Models\Tenant;
use App\Services\StockItemSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockItemSearchServiceTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Location $location;
    private Location $otherLocation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::create(['name' => 'Tenant A']);
        $division = Division::create(['tenant_id' => $this->tenant->id, 'name' => 'Divisão']);
        $this->location = Location::create(['tenant_id' => $this->tenant->id, 'division_id' => $division->id, 'name' => 'Unidade A']);
        $this->otherLocation = Location::create(['tenant_id' => $this->tenant->id, 'division_id' => $division->id, 'name' => 'Unidade B']);
    }

    public function test_abbreviations_and_same_technical_code_produce_a_probable_result(): void
    {
        $item = $this->item('Parafuso com abraçadeira CZ 5-263X/5');
        $results = $this->search('PARAF ABRAC CZ 5-263X/5');
        $result = $results[0];

        $this->assertSame($item->id, $result['item']->id);
        $this->assertGreaterThanOrEqual(90, $result['score']);
        $this->assertSame('probable', $result['level']);
        $this->assertContains('5-263x/5', $result['matched_technical_tokens']);
    }

    public function test_divergent_technical_code_is_strongly_penalized(): void
    {
        $correct = $this->item('Parafuso com abraçadeira CZ 5-263X/5');
        $different = $this->item('Parafuso com abraçadeira CZ 5-264X/5');
        $results = $this->search('PARAF ABRAC CZ 5-263X/5');

        $this->assertSame($correct->id, $results[0]['item']->id);
        $this->assertNotContains($different->id, collect($results)->pluck('item.id')->all());
    }

    public function test_structured_sol_and_dimension_codes_rank_as_strong_matches(): void
    {
        $sol = $this->item('Cruzeta Cardan SOL90-1X 12/15');
        $dimension = $this->item('Cuica Freio 30X30 Haste Longa 16MM');

        $solResult = $this->search('CRUZ CARD SOL90-1X 12/15')[0];
        $dimensionResult = $this->search('CUICA 30X30 16MM')[0];

        $this->assertSame($sol->id, $solResult['item']->id);
        $this->assertGreaterThanOrEqual(75, $solResult['score']);
        $this->assertSame($dimension->id, $dimensionResult['item']->id);
        $this->assertGreaterThanOrEqual(90, $dimensionResult['score']);
    }

    public function test_spl90_does_not_get_confused_with_sol90(): void
    {
        $spl = $this->item('BRAC CZ SPL90-1X C/ABA');
        $this->item('CRUZETA CARDAN SOL90-1X 12/15');

        $result = $this->search('SPL90-1X')[0];

        $this->assertSame($spl->id, $result['item']->id);
        $this->assertSame(95, $result['score']);
    }

    public function test_exact_alias_and_name_are_explicit_and_score_one_hundred(): void
    {
        $item = $this->item('Parafuso com abraçadeira CZ 5-263X/5');
        StockItemAlias::create(['stock_item_id' => $item->id, 'alias' => 'PARAF ABRAC CZ 5-263X/5']);

        $alias = $this->search('PARAF ABRAC CZ 5-263X/5')[0];
        $name = $this->search('parafuso com abracadeira cz 5-263x/5')[0];

        $this->assertSame(100, $alias['score']);
        $this->assertSame('exact_alias', $alias['reason']);
        $this->assertSame(100, $name['score']);
        $this->assertSame('exact_name', $name['reason']);
    }

    public function test_scope_and_active_status_are_mandatory(): void
    {
        $local = $this->item('Parafuso local 5-263X/5');
        $this->item('Parafuso externo 5-263X/5', ['location_id' => $this->otherLocation->id]);
        $this->item('Parafuso inativo 5-263X/5', ['active' => false]);
        $otherTenant = Tenant::create(['name' => 'Tenant B']);
        $division = Division::create(['tenant_id' => $otherTenant->id, 'name' => 'Divisão B']);
        $foreignLocation = Location::create(['tenant_id' => $otherTenant->id, 'division_id' => $division->id, 'name' => 'Unidade B']);
        $this->item('Parafuso outro tenant 5-263X/5', ['tenant_id' => $otherTenant->id, 'location_id' => $foreignLocation->id]);

        $results = $this->search('5-263X/5');

        $this->assertSame([$local->id], collect($results)->pluck('item.id')->all());
    }

    public function test_generic_or_bare_number_queries_are_not_strong_matches(): void
    {
        foreach (range(1, 12) as $number) {
            $this->item("Parafuso genérico {$number}");
        }
        $this->item('OLEO 40 CAMBIO EATON 1LT');

        $this->assertSame([], $this->search('PARAFUSO'));
        $this->assertSame([], $this->search('40'));
    }

    public function test_same_exact_alias_on_different_items_returns_both_deterministically(): void
    {
        $first = $this->item('Primeiro item');
        $second = $this->item('Segundo item');
        StockItemAlias::create(['stock_item_id' => $first->id, 'alias' => 'CODIGO HISTORICO']);
        StockItemAlias::create(['stock_item_id' => $second->id, 'alias' => 'CODIGO HISTORICO']);

        $results = $this->search('CODIGO HISTORICO');

        $this->assertSame([$first->id, $second->id], collect($results)->pluck('item.id')->all());
        $this->assertSame([100, 100], collect($results)->pluck('score')->all());
    }

    private function search(string $query): array
    {
        return app(StockItemSearchService::class)->search($this->tenant->id, $this->location->id, $query);
    }

    private function item(string $name, array $overrides = []): StockItem
    {
        return StockItem::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'location_id' => $this->location->id,
            'name' => $name,
            'unit' => 'UN',
            'quantity' => 0,
            'minimum_quantity' => 0,
            'unit_cost' => 0,
            'active' => true,
        ], $overrides));
    }
}
