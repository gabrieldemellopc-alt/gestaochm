<?php

namespace App\Services;

use App\Models\Division;
use App\Models\User;

class AccessControlDataService
{
    public function __construct(
        private readonly PortalAccessResolver $portalAccessResolver
    ) {
    }

    public function forUser(User $authUser): array
    {
        $divisions = Division::with('locations')
            ->where('tenant_id', $authUser->tenant_id)
            ->orderBy('name')
            ->get();

        $users = User::with([
                'divisionAccesses.division',
                'divisionAccesses.location',
            ])
            ->where('tenant_id', $authUser->tenant_id)
            ->orderBy('name')
            ->get();

        return [
            'users' => $users,
            'divisions' => $divisions,
            'availableModules' => $this->portalAccessResolver->availableModules(),
        ];
    }
}
