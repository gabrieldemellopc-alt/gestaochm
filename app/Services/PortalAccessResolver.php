<?php

namespace App\Services;

use App\Models\Division;
use App\Models\User;
use Illuminate\Support\Collection;

class PortalAccessResolver
{
    private array $moduleRoutes = [
        'fleet' => 'dashboard',
    ];

    public function activeAccesses(User $user): Collection
    {
        return $user->divisionAccesses()
            ->where('active', true)
            ->where('tenant_id', $user->tenant_id)
            ->get();
    }
    public function availableModules(): array
    {
        return [
            'fleet' => 'Frota',
        ];
    }
    public function availableDivisions(User $user): Collection
    {
        $divisionIds = $this->activeAccesses($user)
            ->pluck('division_id')
            ->unique()
            ->values();

        return Division::query()
            ->where('tenant_id', $user->tenant_id)
            ->whereIn('id', $divisionIds)
            ->orderBy('name')
            ->get();
    }

    public function userHasDivisionAccess(User $user, Division $division): bool
    {
        if ((int) $division->tenant_id !== (int) $user->tenant_id) {
            return false;
        }

        return $this->activeAccesses($user)
            ->where('division_id', $division->id)
            ->isNotEmpty();
    }

    public function locationIdsForDivision(User $user, Division $division): Collection
    {
        return $this->activeAccesses($user)
            ->where('division_id', $division->id)
            ->pluck('location_id')
            ->filter()
            ->unique()
            ->values();
    }

    public function modulesForDivision(User $user, Division $division): Collection
    {
        return $this->activeAccesses($user)
            ->where('division_id', $division->id)
            ->pluck('module')
            ->filter()
            ->unique()
            ->values();
    }

    public function routeForModule(?string $module): ?string
    {
        return $this->moduleRoutes[$module] ?? null;
    }

    public function hasMultipleDivisions(User $user): bool
    {
        return $this->availableDivisions($user)->count() > 1;
    }
}