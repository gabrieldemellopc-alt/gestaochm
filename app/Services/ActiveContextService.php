<?php

namespace App\Services;

use App\Models\Division;
use App\Models\Location;
use App\Models\User;
use App\Models\UserDivisionAccess;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ActiveContextService
{
    public function activeDivision(User $user): ?Division
    {
        $divisionId = session('active_division_id');

        if (! $divisionId) {
            return null;
        }

        return $this->availableDivisions($user)
            ->firstWhere('id', (int) $divisionId);
    }

    public function availableDivisions(User $user, string $module = 'fleet'): Collection
    {
        $query = Division::query()
            ->where('tenant_id', $user->tenant_id)
            ->orderBy('name');

        if ($this->hasTenantBypass($user)) {
            return $query->get();
        }

        return $query
            ->whereIn('id', UserDivisionAccess::query()
                ->select('division_id')
                ->where('tenant_id', $user->tenant_id)
                ->where('user_id', $user->id)
                ->where('module', $module)
                ->where('active', true))
            ->get();
    }

    public function availableLocations(User $user, ?int $divisionId = null, string $module = 'fleet'): Collection
    {
        $divisionId ??= session('active_division_id');

        if (! $divisionId || ! $this->canAccessDivision($user, (int) $divisionId, $module)) {
            return collect();
        }

        $locations = Location::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('division_id', $divisionId)
            ->orderBy('name');

        if ($this->hasTenantBypass($user)) {
            return $locations->get();
        }

        $accesses = UserDivisionAccess::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('user_id', $user->id)
            ->where('division_id', $divisionId)
            ->where('module', $module)
            ->where('active', true)
            ->get(['location_id']);

        if (! $accesses->contains(fn ($access) => $access->location_id === null)) {
            $locations->whereIn(
                'id',
                $accesses->pluck('location_id')->filter()->unique()->values()
            );
        }

        return $locations->get();
    }

    public function availableLocationsAcrossDivisions(User $user, string $module = 'fleet'): Collection
    {
        return $this->availableDivisions($user, $module)
            ->flatMap(fn (Division $division) => $this->availableLocations($user, $division->id, $module))
            ->sortBy('name')
            ->values();
    }

    public function canAccessDivision(User $user, int $divisionId, string $module = 'fleet'): bool
    {
        return $this->availableDivisions($user, $module)
            ->contains('id', $divisionId);
    }

    public function canAccessLocation(User $user, int $divisionId, int $locationId, string $module = 'fleet'): bool
    {
        return $this->availableLocations($user, $divisionId, $module)
            ->contains('id', $locationId);
    }

    public function activeLocation(User $user): ?Location
    {
        return $this->initializeActiveLocation($user);
    }

    public function initializeActiveLocation(User $user, ?int $divisionId = null): ?Location
    {
        $divisionId ??= session('active_division_id');
        $locations = $this->availableLocations($user, $divisionId);

        if ($locations->isEmpty()) {
            $this->clearActiveLocation();

            return null;
        }

        $activeLocation = $locations->firstWhere(
            'id',
            (int) session('active_location_id')
        );

        if (! $activeLocation) {
            $activeLocation = $locations->first();

            session([
                'active_location_id' => $activeLocation->id,
            ]);
        }

        return $activeLocation;
    }

    public function revalidateActiveLocation(User $user, int $divisionId): ?Location
    {
        return $this->initializeActiveLocation($user, $divisionId);
    }

    public function setActiveLocation(User $user, int $locationId): Location
    {
        $location = $this->availableLocations($user)
            ->firstWhere('id', $locationId);

        if (! $location) {
            throw ValidationException::withMessages([
                'location_id' => 'A unidade selecionada não está disponível para o seu acesso atual.',
            ]);
        }

        session([
            'active_location_id' => $location->id,
        ]);

        return $location;
    }

    public function clearActiveLocation(): void
    {
        session()->forget('active_location_id');
    }

    private function hasTenantBypass(User $user): bool
    {
        return (int) $user->id === 1
            || UserDivisionAccess::query()
                ->where('tenant_id', $user->tenant_id)
                ->where('user_id', $user->id)
                ->where('profile', 'admin')
                ->where('active', true)
                ->exists();
    }
}
