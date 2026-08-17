<?php

namespace Tests\Unit;

use App\Services\FiscalDocuments\FiscalDocumentItemMatcher;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;
use stdClass;

class FiscalDocumentItemMatcherTest extends TestCase
{
    private FiscalDocumentItemMatcher $matcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->matcher = new FiscalDocumentItemMatcher;
    }

    public function test_normalizes_words_and_preserves_short_numeric_codes(): void
    {
        $this->assertSame(
            ['MOLA', '12', 'TRASEIRA'],
            $this->matcher->tokenize('Mola de 12, traseira')
        );
    }

    public function test_exact_match_is_selected_automatically(): void
    {
        $result = $this->matcher->suggestStockItem(
            'MOLA TRASEIRA REFORCADA',
            'UN',
            collect([$this->item(10, 'Mola traseira reforçada', 'UN', 2, 'Molaria')])
        );

        $this->assertSame(10, $result['suggested_item_id']);
        $this->assertSame('exact', $result['match_level']);
        $this->assertSame(100, $result['match_score']);
    }

    public function test_strong_match_is_selected_when_unambiguous(): void
    {
        $result = $this->matcher->suggestStockItem(
            'MOLA SAPATA FREIO GRANDE BRINCO',
            'UN',
            collect([$this->item(8, 'MOLA SAPATA DE FREIO GRANDE', 'UN')])
        );

        $this->assertSame(8, $result['suggested_item_id']);
        $this->assertSame('strong', $result['match_level']);
        $this->assertGreaterThanOrEqual(85, $result['match_score']);
    }


    public function test_orders_multiple_possible_matches_without_ambiguous_auto_selection(): void
    {
        $result = $this->matcher->suggestStockItem(
            'Mola traseira reforçada caminhão',
            'UN',
            collect([
                $this->item(1, 'Mola traseira caminhão', 'UN'),
                $this->item(2, 'Mola traseira reforçada', 'UN'),
            ])
        );

        $this->assertNull($result['suggested_item_id']);
        $this->assertSame('possible', $result['match_level']);
        $this->assertSame([1, 2], array_column($result['suggestions'], 'id'));
    }

    public function test_weak_match_is_not_suggested(): void
    {
        $result = $this->matcher->suggestStockItem(
            'Filtro de combustível',
            'UN',
            collect([$this->item(1, 'Correia dentada', 'UN')])
        );

        $this->assertNull($result['suggested_item_id']);
        $this->assertSame([], $result['suggestions']);
        $this->assertSame('none', $result['match_level']);
    }

    public function test_suggests_category_by_radical(): void
    {
        $categories = collect([$this->category(4, 'Molaria'), $this->category(5, 'Filtros')]);

        $this->assertSame(4, $this->matcher->suggestCategory('Mola dianteira', $categories)->id);
    }

    public function test_matching_does_not_change_quantity_or_values(): void
    {
        $parsed = [[
            'description' => 'Mola traseira reforçada',
            'unit' => 'UN',
            'quantity' => 7.5,
            'unit_value' => 19.9,
            'discount_value' => 2.3,
            'total_value' => 146.95,
        ]];

        $result = $this->matcher->suggestForParsedItems(
            $parsed,
            collect([$this->item(10, 'Mola traseira reforçada', 'UN', 4, 'Molaria')]),
            collect([$this->category(4, 'Molaria')])
        )[0];

        $this->assertSame(7.5, $result['quantity']);
        $this->assertSame(19.9, $result['unit_value']);
        $this->assertSame(2.3, $result['discount_value']);
        $this->assertSame(146.95, $result['total_value']);
        $this->assertSame(10, $result['stock_item_id']);
        $this->assertSame(4, $result['stock_category_id']);
    }

    public function test_suggests_requested_category_examples(): void
    {
        $categories = collect([$this->category(4, 'Molaria'), $this->category(5, 'Baterias'), $this->category(6, 'Freios')]);
        $this->assertSame(4, $this->matcher->suggestCategory('MOLA SAPATA FREIO GRANDE BRINCO', $categories)->id);
        $this->assertSame(5, $this->matcher->suggestCategory('BATERIA 60 AMPERES', $categories)->id);
        $this->assertSame(6, $this->matcher->suggestCategory('TAMBOR FREIO 10F LONA', $categories)->id);
        $this->assertNull($this->matcher->suggestCategory('DE A DO', $categories));
    }

    public function test_existing_item_category_has_priority_over_textual_category(): void
    {
        $result = $this->matcher->suggestForParsedItems([['description' => 'MOLA SAPATA FREIO GRANDE BRINCO', 'unit' => 'UN']], collect([$this->item(10, 'MOLA SAPATA FREIO GRANDE BRINCO', 'UN', 9, 'Suspensao')]), collect([$this->category(4, 'Molaria'), $this->category(9, 'Suspensao')]))[0];
        $this->assertSame(9, $result['stock_category_id']);
        $this->assertSame(4, $result['textual_suggested_category_id']);
        $this->assertSame(9, $result['category_id']);
    }

    public function test_category_matches_are_ordered_by_score(): void
    {
        $match = $this->matcher->suggestCategoryMatch('MOLA SAPATA FREIO', collect([$this->category(1, 'Molas'), $this->category(2, 'Freios')]));
        $this->assertSame(1, $match['category']->id);
        $this->assertSame(85, $match['score']);
    }
    private function item(int $id, string $name, string $unit, ?int $categoryId = null, ?string $categoryName = null): stdClass
    {
        return (object) [
            'id' => $id,
            'name' => $name,
            'unit' => $unit,
            'stock_category_id' => $categoryId,
            'category' => $categoryName ? (object) ['name' => $categoryName] : null,
        ];
    }

    private function category(int $id, string $name): stdClass
    {
        return (object) ['id' => $id, 'name' => $name];
    }
}
