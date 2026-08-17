<?php

namespace App\Services\FiscalDocuments;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class FiscalDocumentItemMatcher
{
    private const STOPWORDS = ['A', 'O', 'OS', 'AS', 'DE', 'DO', 'DA', 'DOS', 'DAS', 'E', 'COM', 'PARA'];

    public function suggestForParsedItems(array $parsedItems, Collection $stockItems, Collection $categories): array
    {
        return array_map(function (array $parsed) use ($stockItems, $categories) {
            $match = $this->suggestStockItem(
                (string) ($parsed['description'] ?? ''),
                $parsed['unit'] ?? null,
                $stockItems
            );

            $categoryMatch = $this->suggestCategoryMatch((string) ($parsed['description'] ?? ''), $categories);
            $categoryId = $match['suggested_category_id']
                ?? ($categoryMatch['category']->id ?? null);

            return array_merge($parsed, $match, [
                'stock_item_id' => $match['suggested_item_id'],
                'stock_category_id' => $categoryId,
                'category_id' => $categoryId,
                'category_suggested_id' => $categoryMatch['category']->id ?? null,
                'textual_suggested_category_id' => $categoryMatch['category']->id ?? null,
                'textual_suggested_category_name' => $categoryMatch['category']->name ?? null,
                'textual_category_score' => $categoryMatch['score'] ?? 0,
                'textual_category_reason' => $categoryMatch['reason'] ?? null,
                'action' => $match['suggested_item_id'] ? 'existing' : 'new',
            ]);
        }, $parsedItems);
    }

    public function suggestStockItem(string $description, ?string $unit, Collection $stockItems): array
    {
        $invoiceNormalized = $this->normalizeText($description);
        $invoiceTokens = $this->tokenize($description);
        $suggestions = $stockItems
            ->map(function ($item) use ($invoiceNormalized, $invoiceTokens, $unit) {
                $scored = $this->scoreItemMatch($invoiceNormalized, $invoiceTokens, $unit, $item);

                return array_merge($scored, [
                    'id' => (int) $item->id,
                    'name' => (string) $item->name,
                    'unit' => (string) $item->unit,
                    'category_id' => $item->stock_category_id ? (int) $item->stock_category_id : null,
                    'category_name' => $item->category?->name,
                ]);
            })
            ->filter(fn (array $candidate) => $candidate['matched_tokens'] >= 2 || $candidate['match_level'] === 'exact')
            ->sortByDesc('score')
            ->take(5)
            ->values();

        $first = $suggestions->first();
        $second = $suggestions->get(1);
        $automatic = $first && (
            $first['match_level'] === 'exact'
            || ($first['score'] >= 85 && (! $second || ($first['score'] - $second['score']) >= 8))
            || ($first['matched_tokens'] >= 4 && $first['invoice_coverage'] >= .75 && (! $second || ($first['score'] - $second['score']) >= 8))
        );

        return [
            'suggested_item_id' => $automatic ? $first['id'] : null,
            'suggested_category_id' => $automatic ? $first['category_id'] : null,
            'match_score' => $first['score'] ?? 0,
            'match_level' => $automatic ? $first['match_level'] : ($first ? 'possible' : 'none'),
            'suggestions' => $suggestions->all(),
        ];
    }

    public function suggestCategory(string $description, Collection $categories): mixed
    {
        return $this->suggestCategoryMatch($description, $categories)['category'] ?? null;
    }

    public function suggestCategoryMatch(string $description, Collection $categories): array
    {
        $tokens = $this->tokenize($description);

        return $categories
            ->map(function ($category) use ($tokens) {
                $categoryTokens = $this->tokenize((string) $category->name);
                $matches = collect($tokens)->map(function (string $token) use ($categoryTokens) {
                    $best = collect($categoryTokens)->map(fn (string $categoryToken) => $this->scoreCategoryToken($token, $categoryToken))->sortByDesc('score')->first();
                    return $best ? array_merge($best, ['token' => $token]) : null;
                })->filter(fn ($match) => $match && $match['score'] >= 65)->values();
                $best = $matches->sortByDesc('score')->first();
                $matchedTokens = $matches->pluck('token')->unique()->count();
                $score = $best ? min(100, $best['score'] + max(0, $matchedTokens - 1) * 5) : 0;

                return ['category' => $category, 'score' => $score, 'matched_tokens' => $matchedTokens, 'reason' => $best ? 'similaridade com '.$best['token'] : null];
            })
            ->filter(fn (array $entry) => $entry['score'] >= 65)
            ->sortByDesc('matched_tokens')
            ->sortByDesc('score')
            ->values()
            ->first() ?? [];
    }

    public function normalizeText(string $text): string
    {
        $ascii = Str::upper(Str::ascii($text));
        return trim(preg_replace('/\s+/', ' ', preg_replace('/[^A-Z0-9\/]+/', ' ', $ascii)));
    }

    public function tokenize(string $text): array
    {
        return collect(explode(' ', $this->normalizeText($text)))
            ->filter(fn (string $token) => (strlen($token) >= 3 || preg_match('/\\d/', $token)) && ! in_array($token, self::STOPWORDS, true))
            ->unique()
            ->values()
            ->all();
    }

    private function scoreCategoryToken(string $token, string $categoryToken): array
    {
        if (strlen($token) < 4 || strlen($categoryToken) < 4) return ['score' => 0];
        if ($token === $categoryToken) return ['score' => 100];
        if (str_starts_with($categoryToken, $token)) return ['score' => 85];
        if (str_contains($categoryToken, $token)) return ['score' => 75];
        if (str_starts_with($token, $categoryToken)) return ['score' => 75];
        $prefix = 0; $limit = min(strlen($token), strlen($categoryToken));
        while ($prefix < $limit && $token[$prefix] === $categoryToken[$prefix]) $prefix++;
        return ['score' => $prefix >= 4 ? 65 : 0];
    }
    private function scoreItemMatch(string $invoiceNormalized, array $invoiceTokens, ?string $unit, mixed $item): array
    {
        $stockNormalized = $this->normalizeText((string) $item->name);
        $stockTokens = $this->tokenize((string) $item->name);

        if ($invoiceNormalized !== '' && $invoiceNormalized === $stockNormalized) {
            return ['score'=>100, 'match_level'=>'exact', 'matched_tokens'=>count($invoiceTokens), 'invoice_coverage'=>1.0, 'stock_coverage'=>1.0, 'reason'=>count($invoiceTokens).' palavras coincidem'];
        }

        $common = array_values(array_intersect($invoiceTokens, $stockTokens));
        $matched = count($common);
        $invoiceCoverage = count($invoiceTokens) ? $matched / count($invoiceTokens) : 0;
        $stockCoverage = count($stockTokens) ? $matched / count($stockTokens) : 0;
        $score = ($invoiceCoverage * 55) + ($stockCoverage * 35);

        if (($invoiceTokens[0] ?? null) === ($stockTokens[0] ?? null) && $invoiceTokens !== []) {
            $score += 5;
        }

        if ($unit && $item->unit && Str::upper($unit) === Str::upper((string) $item->unit)) {
            $score += 5;
        }

        $invoiceCodes = array_filter($invoiceTokens, fn (string $token) => preg_match('/\d/', $token));
        $stockCodes = array_filter($stockTokens, fn (string $token) => preg_match('/\d/', $token));
        if ($invoiceCodes && $stockCodes && array_intersect($invoiceCodes, $stockCodes)) {
            $score += 8;
        }

        $score = min(99, (int) round($score));

        return [
            'score' => $score,
            'match_level' => $score >= 75 && $matched >= 3 ? 'strong' : ($matched >= 2 ? 'possible' : 'none'),
            'matched_tokens' => $matched,
            'invoice_coverage' => round($invoiceCoverage, 4),
            'stock_coverage' => round($stockCoverage, 4),
            'reason' => $matched.' '.Str::plural('palavra', $matched).' coincidem',
        ];
    }
}
