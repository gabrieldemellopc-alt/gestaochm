<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Produces conservative, deterministic search representations for technical
 * stock-item descriptions. It intentionally has no persistence concerns.
 */
class StockItemNormalizer
{
    private const ABBREVIATIONS = [
        'paraf' => 'parafuso',
        'abrac' => 'abracadeira',
        'rolam' => 'rolamento',
        'mang' => 'mangueira',
        'conex' => 'conexao',
        'valv' => 'valvula',
    ];

    /**
     * Normalizes display text without splitting meaningful technical codes.
     */
    public function normalizeName(string $name): string
    {
        $normalized = Str::of($name)->ascii()->lower()->trim()->value();

        // Keep slash and hyphen inside tokens; all other punctuation is a
        // separator. This preserves values such as 5-263x/5 and SOL90-1X.
        $normalized = preg_replace('/[^a-z0-9\/\-\s]+/', ' ', $normalized) ?? '';
        $tokens = preg_split('/\s+/', trim($normalized), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return implode(' ', array_values(array_filter(array_map(
            static fn (string $token): string => trim($token, '-/'),
            $tokens,
        ))));
    }

    /** @return array<int, string> */
    public function tokenize(string $name): array
    {
        $normalized = $this->normalizeName($name);

        if ($normalized === '') {
            return [];
        }

        return array_values(array_unique(explode(' ', $normalized)));
    }

    /** @return array<int, string> */
    public function technicalTokens(string $name): array
    {
        return array_values(array_filter(
            $this->tokenize($name),
            fn (string $token): bool => $this->isTechnicalToken($token),
        ));
    }

    /** @return array<int, string> */
    public function expandedTokens(string $name): array
    {
        $expanded = [];

        foreach ($this->tokenize($name) as $token) {
            $expanded[] = $token;

            if (isset(self::ABBREVIATIONS[$token])) {
                $expanded[] = self::ABBREVIATIONS[$token];
            }
        }

        return array_values(array_unique($expanded));
    }

    private function isTechnicalToken(string $token): bool
    {
        // A bare number (for example, oil viscosity "40") is deliberately
        // excluded. Structural punctuation or a recognized unit is required.
        return (bool) preg_match(
            '/^(?:\d+[a-z0-9]*[-\/][a-z0-9\/\-]*|[a-z]+\d+[a-z0-9]*[-\/][a-z0-9\/\-]*|\d+x\d+|\d+(?:mm|cm|mt|kg|g|lt|ml|v|w|a))$/',
            $token,
        );
    }
}
