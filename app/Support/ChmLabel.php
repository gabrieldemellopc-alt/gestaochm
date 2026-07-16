<?php

namespace App\Support;

use Illuminate\Support\Str;

class ChmLabel
{
    public static function for(string $domain, mixed $value, ?string $fallback = null): string
    {
        if ($value === null || $value === '') {
            return $fallback ?? '-';
        }

        $key = (string) $value;
        $label = config("chm_labels.{$domain}.{$key}");

        if (is_string($label) && $label !== '') {
            return $label;
        }

        if ($fallback !== null && $fallback !== '') {
            return $fallback;
        }

        return Str::of($key)
            ->replace(['-', '_'], ' ')
            ->headline()
            ->toString();
    }
    public static function knownToken(array $domains, mixed $value, ?string $fallback = null): string
    {
        if ($value === null || $value === '') {
            return $fallback ?? '-';
        }

        $key = trim((string) $value);

        foreach ($domains as $domain) {
            $labels = config("chm_labels.{$domain}", []);

            if (is_array($labels) && array_key_exists($key, $labels)) {
                return (string) $labels[$key];
            }
        }

        return $key !== '' ? $key : ($fallback ?? '-');
    }
}
