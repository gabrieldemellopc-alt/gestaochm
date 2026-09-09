<?php

namespace App\Services;

use App\Models\User;

/** Explicit, non-HTTP context used only by controlled fuel operations. */
final readonly class FuelOperationContext
{
    public function __construct(
        public User $user,
        public int $tenantId,
        public int $divisionId,
        public int $locationId,
        public bool $isHistoricalImport = false,
        public ?int $importBatchId = null,
        public bool $allowLegacyBalanceAnomalies = false,
    ) {}
}
