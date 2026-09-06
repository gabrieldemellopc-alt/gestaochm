<?php

namespace Tests\Unit;

use App\Services\StockItemNormalizer;
use Tests\TestCase;

class StockItemNormalizerTest extends TestCase
{
    private StockItemNormalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->normalizer = new StockItemNormalizer();
    }

    public function test_it_preserves_a_compound_technical_code(): void
    {
        $name = 'PARAF ABRAC CZ 5-263X/5';

        $this->assertSame('paraf abrac cz 5-263x/5', $this->normalizer->normalizeName($name));
        $this->assertSame(['paraf', 'abrac', 'cz', '5-263x/5'], $this->normalizer->tokenize($name));
        $this->assertSame(['5-263x/5'], $this->normalizer->technicalTokens($name));
    }

    public function test_it_tokenizes_the_full_description_variant(): void
    {
        $this->assertSame(
            ['parafuso', 'com', 'abracadeira', 'cz', '5-263x/5'],
            $this->normalizer->tokenize('Parafuso com abraçadeira CZ 5-263X/5'),
        );
    }

    public function test_it_preserves_structured_codes_and_measurements(): void
    {
        $this->assertSame(
            ['sol90-1x', '12/15'],
            $this->normalizer->technicalTokens('CRUZETA CARDAN SOL90-1X 12/15'),
        );
        $this->assertSame(['spl90-1x'], $this->normalizer->technicalTokens('BRAC CZ SPL90-1X C/ABA'));
        $this->assertSame(['30x30', '16mm'], $this->normalizer->technicalTokens('CUICA FREIO 30X30 HASTE LONGA 16MM'));
        $this->assertSame(['45mm'], $this->normalizer->technicalTokens('REFIL C/ROLAMENTO CENTRO 45MM'));
    }

    public function test_it_does_not_treat_a_bare_number_as_a_strong_technical_token(): void
    {
        $this->assertSame(['1lt'], $this->normalizer->technicalTokens('OLEO 40 CAMBIO EATON 1LT'));
    }

    public function test_it_removes_accents_and_decorative_punctuation(): void
    {
        $this->assertSame('abracadeira', $this->normalizer->normalizeName('Abraçadeira'));
        $this->assertSame(['paraf', 'abrac', 'cz'], $this->normalizer->tokenize('PARAF. ABRAC. CZ'));
    }

    public function test_it_expands_only_the_explicit_abbreviation_dictionary(): void
    {
        $this->assertSame(
            ['paraf', 'parafuso', 'abrac', 'abracadeira', 'cz', '5-263x/5'],
            $this->normalizer->expandedTokens('PARAF ABRAC CZ 5-263X/5'),
        );
        $this->assertSame(['cx', 'kit', 'ref', 'brac'], $this->normalizer->expandedTokens('CX KIT REF BRAC'));
    }
}
