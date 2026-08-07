<?php

namespace App\Services\Reports;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class ReportPayloadSanitizer
{
    public function costs(array $payload, bool $canViewCosts): array
    {
        if ($canViewCosts) {
            return $payload;
        }

        return $this->sanitizeValue($payload);
    }

    private function sanitizeValue(mixed $value, ?string $key = null): mixed
    {
        if ($key !== null && $this->isMonetaryKey($key)) {
            return null;
        }

        if ($value instanceof Model) {
            foreach (array_keys($value->getAttributes()) as $attribute) {
                if ($this->isMonetaryKey($attribute)) {
                    $value->setAttribute($attribute, null);
                }
            }

            foreach ($value->getRelations() as $relation => $related) {
                $value->setRelation($relation, $this->sanitizeValue($related));
            }

            return $value;
        }

        if ($value instanceof Collection) {
            return $value->map(fn (mixed $item) => $this->sanitizeValue($item));
        }

        if (is_array($value)) {
            foreach ($value as $itemKey => $item) {
                $value[$itemKey] = $this->sanitizeValue($item, is_string($itemKey) ? $itemKey : null);
            }
        }

        return $value;
    }

    private function isMonetaryKey(string $key): bool
    {
        if (
            str_starts_with($key, 'can_')
            || str_starts_with($key, 'include_')
            || str_starts_with($key, 'contains_')
            || str_contains($key, '_is_')
            || str_contains($key, '_has_')
            || str_ends_with($key, '_policy')
            || str_ends_with($key, '_flags')
            || str_ends_with($key, '_source')
            || str_ends_with($key, '_note')
        ) {
            return false;
        }

        return $key === 'amount'
            || str_ends_with($key, '_amount')
            || str_contains($key, 'cost')
            || $key === 'estimated_inventory_value'
            || $key === 'estimated_value';
    }
}
