<?php

namespace Tests\Feature;

use App\Models\StockItem;
use App\Models\StockItemAlias;
use App\Models\Tenant;
use App\Models\Division;
use App\Models\Location;
use App\Console\Commands\BackfillStockItemNormalizedNames;
use App\Services\StockItemNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\QueryException;
use Tests\TestCase;

class StockItemNormalizationPersistenceTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Location $location;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'Tenant de teste']);
        $division = Division::create(['tenant_id' => $this->tenant->id, 'name' => 'Divisão de teste']);
        $this->location = Location::create([
            'tenant_id' => $this->tenant->id,
            'division_id' => $division->id,
            'name' => 'Unidade de teste',
        ]);
    }

    public function test_stock_item_name_is_normalized_on_create_and_update(): void
    {
        $item = StockItem::create($this->itemAttributes(['name' => 'PARAF. ABRAC. CZ 5-263X/5']));

        $this->assertSame('paraf abrac cz 5-263x/5', $item->normalized_name);

        $item->update(['name' => 'Abraçadeira SOL90-1X']);

        $this->assertSame('abracadeira sol90-1x', $item->fresh()->normalized_name);
    }

    public function test_aliases_are_normalized_and_unique_per_stock_item_only(): void
    {
        $item = StockItem::create($this->itemAttributes());
        $otherItem = StockItem::create($this->itemAttributes(['name' => 'Outro item']));

        $alias = StockItemAlias::create(['stock_item_id' => $item->id, 'alias' => 'PARAF. ABRAÇ. CZ', 'source' => 'manual']);

        $this->assertSame('paraf abrac cz', $alias->normalized_alias);
        $alias->update(['alias' => 'Conex. 12/15']);
        $this->assertSame('conex 12/15', $alias->fresh()->normalized_alias);
        $this->expectException(QueryException::class);
        StockItemAlias::create(['stock_item_id' => $item->id, 'alias' => 'CONEX. 12/15']);
    }

    public function test_same_normalized_alias_can_belong_to_another_stock_item(): void
    {
        $first = StockItem::create($this->itemAttributes());
        $second = StockItem::create($this->itemAttributes(['name' => 'Segundo item']));

        StockItemAlias::create(['stock_item_id' => $first->id, 'alias' => 'PARAF ABRAC']);
        StockItemAlias::create(['stock_item_id' => $second->id, 'alias' => 'Paraf. Abrac']);

        $this->assertSame(2, StockItemAlias::count());
    }

    public function test_backfill_is_dry_run_by_default(): void
    {
        $first = $this->insertUnnormalizedItem('PARAF ABRAC CZ 5-263X/5');
        $second = $this->insertUnnormalizedItem('Paraf Abrac CZ 5-263X/5');

        Artisan::call('chm:backfill-stock-item-normalized-names');

        $this->assertNull(DB::table('stock_items')->where('id', $first)->value('normalized_name'));
        $this->assertNull(DB::table('stock_items')->where('id', $second)->value('normalized_name'));
        $this->assertSame(2, DB::table('stock_items')->whereIn('id', [$first, $second])->count());
    }

    public function test_backfill_populates_normalized_names_without_changing_or_deduplicating_items(): void
    {
        $first = $this->insertUnnormalizedItem('PARAF ABRAC CZ 5-263X/5');
        $second = $this->insertUnnormalizedItem('Paraf Abrac CZ 5-263X/5');

        $stats = app(BackfillStockItemNormalizedNames::class)
            ->backfill(app(StockItemNormalizer::class), true);

        $this->assertSame(['scanned' => 2, 'would_update' => 2, 'unchanged' => 0], $stats);

        $this->assertSame('paraf abrac cz 5-263x/5', DB::table('stock_items')->where('id', $first)->value('normalized_name'));
        $this->assertSame('paraf abrac cz 5-263x/5', DB::table('stock_items')->where('id', $second)->value('normalized_name'));
        $this->assertSame('PARAF ABRAC CZ 5-263X/5', DB::table('stock_items')->where('id', $first)->value('name'));
        $this->assertSame(2, DB::table('stock_items')->whereIn('id', [$first, $second])->count());
    }

    public function test_aliases_cascade_when_their_stock_item_is_deleted(): void
    {
        $item = StockItem::create($this->itemAttributes());
        $alias = StockItemAlias::create(['stock_item_id' => $item->id, 'alias' => 'Paraf Abrac']);

        $item->delete();

        $this->assertDatabaseMissing('stock_item_aliases', ['id' => $alias->id]);
    }

    private function itemAttributes(array $overrides = []): array
    {
        return array_merge([
            'tenant_id' => $this->tenant->id,
            'location_id' => $this->location->id,
            'name' => 'Item padrão',
            'unit' => 'UN',
            'quantity' => 0,
            'minimum_quantity' => 0,
            'unit_cost' => 0,
            'active' => true,
        ], $overrides);
    }

    private function insertUnnormalizedItem(string $name): int
    {
        return DB::table('stock_items')->insertGetId([
            'tenant_id' => $this->tenant->id,
            'location_id' => $this->location->id,
            'name' => $name,
            'unit' => 'UN',
            'quantity' => 0,
            'minimum_quantity' => 0,
            'unit_cost' => 0,
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
