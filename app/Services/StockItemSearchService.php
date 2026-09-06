<?php

namespace App\Services;

use App\Models\StockItem;
use Illuminate\Support\Collection;

class StockItemSearchService
{
    private const STOP_WORDS = ['com', 'de', 'do', 'da', 'para'];

    public function __construct(private StockItemNormalizer $normalizer)
    {
    }

    /**
     * @param array{unit?: string, stock_category_id?: int, brand?: string, limit?: int, candidate_limit?: int} $options
     * @return array<int, array{item: StockItem, score: int, level: string, reason: string, matched_tokens: array<int, string>, matched_technical_tokens: array<int, string>, matched_alias: ?string}>
     */
    public function search(int $tenantId, int $locationId, string $query, array $options = []): array
    {
        $normalized = $this->normalizer->normalizeName($query);

        if ($normalized === '') {
            return [];
        }

        $candidateLimit = min(50, max(1, (int) ($options['candidate_limit'] ?? 50)));
        $resultLimit = min(5, max(1, (int) ($options['limit'] ?? 5)));
        $signals = $this->candidateSignals($query);

        $candidates = StockItem::query()
            ->with([
                'aliases:id,stock_item_id,alias,normalized_alias',
                'category:id,name',
            ])
            ->where('tenant_id', $tenantId)
            ->where('location_id', $locationId)
            ->where('active', true)
            ->where(function ($items) use ($normalized, $signals): void {
                $items->where('normalized_name', $normalized)
                    ->orWhereHas('aliases', fn ($aliases) => $aliases->where('normalized_alias', $normalized));

                foreach ($signals as $signal) {
                    $like = '%'.$signal.'%';
                    $items->orWhere('normalized_name', 'like', $like)
                        ->orWhereHas('aliases', fn ($aliases) => $aliases->where('normalized_alias', 'like', $like));
                }
            })
            ->orderBy('id')
            ->limit($candidateLimit)
            ->get();

        return $candidates
            ->map(fn (StockItem $item) => $this->score($item, $normalized, $query, $options))
            ->filter()
            ->sort(function (array $left, array $right): int {
                return [$right['score'], $this->exactRank($right), $left['item']->id]
                    <=> [$left['score'], $this->exactRank($left), $right['item']->id];
            })
            ->take($resultLimit)
            ->values()
            ->all();
    }

    /** @return array<int, string> */
    private function candidateSignals(string $query): array
    {
        $tokens = $this->normalizer->tokenize($query);
        $technical = $this->normalizer->technicalTokens($query);

        return array_values(array_unique(array_filter($tokens, function (string $token) use ($technical): bool {
            return in_array($token, $technical, true)
                || (strlen($token) >= 3 && ! in_array($token, self::STOP_WORDS, true));
        })));
    }

    /** @param array<string, mixed> $options */
    private function score(StockItem $item, string $normalizedQuery, string $query, array $options): ?array
    {
        $exactAlias = $item->aliases->firstWhere('normalized_alias', $normalizedQuery);

        if ($exactAlias) {
            return $this->result($item, 100, 'exact', 'exact_alias', [], [], $exactAlias->alias);
        }

        if ($item->normalized_name === $normalizedQuery) {
            return $this->result($item, 100, 'exact', 'exact_name', [], [], null);
        }

        $best = $this->scoreText($query, (string) $item->name, $options);
        $matchedAlias = null;

        foreach ($item->aliases as $alias) {
            $scoredAlias = $this->scoreText($query, (string) $alias->alias, $options);
            if ($scoredAlias['score'] > $best['score']) {
                $best = $scoredAlias;
                $matchedAlias = $alias->alias;
            }
        }

        $best['score'] += $this->contextBonus($item, $options);
        $best['score'] = min(99, $best['score']);

        if ($best['score'] < 75) {
            return null;
        }

        return $this->result(
            $item,
            $best['score'],
            $best['score'] >= 90 ? 'probable' : 'similar',
            $best['reason'],
            $best['matched_tokens'],
            $best['matched_technical_tokens'],
            $matchedAlias,
        );
    }

    /** @param array<string, mixed> $options @return array{score: int, reason: string, matched_tokens: array<int, string>, matched_technical_tokens: array<int, string>} */
    private function scoreText(string $query, string $candidate, array $options): array
    {
        $queryTokens = $this->meaningfulTokens($this->normalizer->expandedTokens($query));
        $candidateTokens = $this->meaningfulTokens($this->normalizer->expandedTokens($candidate));
        $queryTechnical = $this->normalizer->technicalTokens($query);
        $candidateTechnical = $this->normalizer->technicalTokens($candidate);
        $matchedTechnical = array_values(array_intersect($queryTechnical, $candidateTechnical));
        $matchedTokens = array_values(array_diff(array_intersect($queryTokens, $candidateTokens), $matchedTechnical));

        $technicalPoints = min(70, count($matchedTechnical) * 45);
        $normalPoints = collect($matchedTokens)->sum(fn (string $token) => strlen($token) <= 2 ? 2 : 12);
        $score = $technicalPoints + $normalPoints;

        if (count($matchedTechnical) >= 2) {
            $score += 10;
        }

        if ($matchedTechnical !== [] && count($matchedTokens) >= 2) {
            $score += 20;
        }

        if (count($queryTechnical) === 1 && count($queryTokens) === 1 && count($matchedTechnical) === 1) {
            $score = max($score, 95);
        }

        // Different structured codes are a strong warning. Do not reject a
        // candidate outright: descriptions can legitimately have many codes.
        if ($queryTechnical !== [] && $candidateTechnical !== [] && $matchedTechnical === []) {
            $score -= 40;
        }

        $score = max(0, min(99, $score));

        return [
            'score' => $score,
            'reason' => $matchedTechnical !== [] ? 'technical_and_tokens' : 'tokens',
            'matched_tokens' => $matchedTokens,
            'matched_technical_tokens' => $matchedTechnical,
        ];
    }

    /** @return array<int, string> */
    private function meaningfulTokens(array $tokens): array
    {
        return array_values(array_filter($tokens, fn (string $token) => ! in_array($token, self::STOP_WORDS, true)));
    }

    /** @param array<int, string> $matchedTokens @param array<int, string> $matchedTechnical */
    private function result(StockItem $item, int $score, string $level, string $reason, array $matchedTokens, array $matchedTechnical, ?string $matchedAlias): array
    {
        return [
            'item' => $item,
            'score' => $score,
            'level' => $level,
            'reason' => $reason,
            'matched_tokens' => $matchedTokens,
            'matched_technical_tokens' => $matchedTechnical,
            'matched_alias' => $matchedAlias,
        ];
    }

    private function exactRank(array $result): int
    {
        return match ($result['reason']) {
            'exact_alias' => 2,
            'exact_name' => 1,
            default => 0,
        };
    }

    /** @param array<string, mixed> $options */
    private function contextBonus(StockItem $item, array $options): int
    {
        $bonus = 0;

        if (($options['unit'] ?? null) !== null && $options['unit'] !== '' && (string) $options['unit'] === (string) $item->unit) {
            $bonus += 5;
        }
        if (($options['stock_category_id'] ?? null) !== null && (int) $options['stock_category_id'] === (int) $item->stock_category_id) {
            $bonus += 4;
        }
        if (($options['brand'] ?? null) !== null && $options['brand'] !== '' && $this->normalizer->normalizeName((string) $options['brand']) === $this->normalizer->normalizeName((string) $item->brand)) {
            $bonus += 4;
        }

        return $bonus;
    }
}
