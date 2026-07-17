<?php

namespace App\Services\Permissions;

use App\Models\Division;
use App\Models\Location;
use App\Models\ProfilePermissionOverride;
use App\Models\User;
use App\Models\UserDivisionAccess;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProfilePermissionService
{
    public function catalog(): array
    {
        return config('chm_permissions.groups', []);
    }

    public function managedProfiles(): array
    {
        return config('chm_permissions.managed_profiles', ['supervisor']);
    }

    public function modules(): array
    {
        return config('chm_permissions.modules', ['fleet' => 'Frota']);
    }

    public function matrix(User $user, array $filters = []): array
    {
        $scope = $this->resolveScope($user, $filters);
        $overrides = $this->overridesForScope($user, $scope);

        $groups = collect($this->catalog())
            ->map(function (array $group) use ($scope, $overrides) {
                $permissions = collect($group['permissions'] ?? [])
                    ->map(function (array $permission, string $key) use ($scope, $overrides) {
                        $default = (bool) Arr::get($permission, "default.{$scope['profile']}", false);
                        $override = $overrides->get($key);

                        return [
                            'key' => $key,
                            'label' => $permission['label'] ?? $key,
                            'description' => $permission['description'] ?? null,
                            'default' => $default,
                            'allowed' => $override?->allowed ?? $default,
                            'has_override' => (bool) $override,
                        ];
                    })
                    ->values()
                    ->all();

                return [
                    'label' => $group['label'] ?? 'Grupo',
                    'description' => $group['description'] ?? null,
                    'permissions' => $permissions,
                ];
            })
            ->all();

        return [
            'scope' => $scope,
            'groups' => $groups,
            'catalog_keys' => $this->permissionKeys(),
            'divisions' => $this->divisionsForUser($user),
            'locations' => $this->locationsForScope($user, $scope),
            'profiles' => config('chm_permissions.profiles', ['supervisor' => 'Supervisor']),
            'modules' => $this->modules(),
        ];
    }

    public function update(User $user, array $data): void
    {
        $scope = $this->resolveScope($user, $data);
        $keys = $this->permissionKeys();
        $allowedKeys = collect($data['permissions'] ?? [])
            ->filter(fn ($value) => (bool) $value)
            ->keys()
            ->intersect($keys)
            ->values();

        DB::transaction(function () use ($user, $scope, $keys, $allowedKeys) {
            foreach ($keys as $permissionKey) {
                $default = $this->defaultFor($scope['profile'], $permissionKey);
                $allowed = $allowedKeys->contains($permissionKey);

                $attributes = $this->scopeAttributes($user, $scope, $permissionKey);

                if ($allowed === $default) {
                    ProfilePermissionOverride::query()
                        ->where($attributes)
                        ->delete();

                    continue;
                }

                ProfilePermissionOverride::updateOrCreate(
                    $attributes,
                    [
                        'allowed' => $allowed,
                        'updated_by' => $user->id,
                        'created_by' => $user->id,
                    ]
                );
            }
        });
    }

    public function allows(?User $user, string $permissionKey, array $scope = []): bool
    {
        if (! $user) {
            return false;
        }

        if ((int) $user->id === 1 || userHasProfile('admin')) {
            return true;
        }

        $profile = $scope['profile'] ?? $this->currentProfile($user);

        if (! $profile) {
            return false;
        }

        if (! $this->permissionKeys()->contains($permissionKey)) {
            return false;
        }

        $default = $this->defaultFor($profile, $permissionKey);
        $tenantId = $user->tenant_id;
        $divisionId = $scope['division_id'] ?? session('active_division_id');
        $locationId = $scope['location_id'] ?? session('active_location_id');
        $module = $scope['module'] ?? 'fleet';

        $override = $this->bestOverride($tenantId, $divisionId, $locationId, $module, $profile, $permissionKey);

        return $override?->allowed ?? $default;
    }

    private function resolveScope(User $user, array $filters): array
    {
        $profile = $filters['profile'] ?? 'supervisor';
        $module = $filters['module'] ?? 'fleet';
        $divisionId = $filters['division_id'] ?? session('active_division_id');
        $locationId = $filters['location_id'] ?? session('active_location_id');

        if (! in_array($profile, $this->managedProfiles(), true)) {
            throw ValidationException::withMessages([
                'profile' => 'Este perfil ainda não está disponível para configuração.',
            ]);
        }

        if (! array_key_exists($module, $this->modules())) {
            throw ValidationException::withMessages([
                'module' => 'Módulo inválido para configuração de permissões.',
            ]);
        }

        if (! $divisionId) {
            $divisionId = $this->divisionsForUser($user)->first()?->id;
        }

        if (! $divisionId || ! $this->canManageDivision($user, (int) $divisionId)) {
            throw ValidationException::withMessages([
                'division_id' => 'Selecione uma divisão válida dentro do seu tenant.',
            ]);
        }

        if ($locationId !== null && $locationId !== '') {
            $location = Location::query()
                ->where('tenant_id', $user->tenant_id)
                ->where('division_id', $divisionId)
                ->whereKey($locationId)
                ->first();

            if (! $location || ! $this->canManageLocation($user, (int) $divisionId, (int) $locationId)) {
                throw ValidationException::withMessages([
                    'location_id' => 'Selecione uma unidade válida para a divisão escolhida.',
                ]);
            }

            $locationId = (int) $locationId;
        } else {
            $locationId = null;
        }

        return [
            'tenant_id' => $user->tenant_id,
            'division_id' => (int) $divisionId,
            'location_id' => $locationId,
            'module' => $module,
            'profile' => $profile,
        ];
    }

    private function overridesForScope(User $user, array $scope): Collection
    {
        return ProfilePermissionOverride::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('division_id', $scope['division_id'])
            ->where('location_id', $scope['location_id'])
            ->where('module', $scope['module'])
            ->where('profile', $scope['profile'])
            ->get()
            ->keyBy('permission_key');
    }

    private function bestOverride(int $tenantId, ?int $divisionId, ?int $locationId, string $module, string $profile, string $permissionKey): ?ProfilePermissionOverride
    {
        return ProfilePermissionOverride::query()
            ->where('tenant_id', $tenantId)
            ->where('module', $module)
            ->where('profile', $profile)
            ->where('permission_key', $permissionKey)
            ->where(function ($query) use ($divisionId) {
                $query
                    ->where('division_id', $divisionId)
                    ->orWhereNull('division_id');
            })
            ->where(function ($query) use ($locationId) {
                $query
                    ->where('location_id', $locationId)
                    ->orWhereNull('location_id');
            })
            ->orderByRaw('division_id is null')
            ->orderByRaw('location_id is null')
            ->first();
    }

    private function scopeAttributes(User $user, array $scope, string $permissionKey): array
    {
        return [
            'tenant_id' => $user->tenant_id,
            'division_id' => $scope['division_id'],
            'location_id' => $scope['location_id'],
            'module' => $scope['module'],
            'profile' => $scope['profile'],
            'permission_key' => $permissionKey,
        ];
    }

    private function defaultFor(string $profile, string $permissionKey): bool
    {
        foreach ($this->catalog() as $group) {
            if (isset($group['permissions'][$permissionKey])) {
                return (bool) Arr::get($group['permissions'][$permissionKey], "default.{$profile}", false);
            }
        }

        return false;
    }

    private function permissionKeys(): Collection
    {
        return collect($this->catalog())
            ->flatMap(fn (array $group) => array_keys($group['permissions'] ?? []))
            ->values();
    }

    private function divisionsForUser(User $user): Collection
    {
        if ((int) $user->id === 1 || userHasProfile('admin')) {
            return Division::query()
                ->where('tenant_id', $user->tenant_id)
                ->orderBy('name')
                ->get();
        }

        return Division::query()
            ->where('tenant_id', $user->tenant_id)
            ->whereIn('id', UserDivisionAccess::query()
                ->select('division_id')
                ->where('tenant_id', $user->tenant_id)
                ->where('user_id', $user->id)
                ->where('profile', 'manager')
                ->where('active', true))
            ->orderBy('name')
            ->get();
    }

    private function locationsForScope(User $user, array $scope): Collection
    {
        $query = Location::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('division_id', $scope['division_id'])
            ->orderBy('name');

        if ((int) $user->id === 1 || userHasProfile('admin')) {
            return $query->get();
        }

        $hasGlobalAccess = UserDivisionAccess::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('user_id', $user->id)
            ->where('division_id', $scope['division_id'])
            ->where('profile', 'manager')
            ->where('active', true)
            ->whereNull('location_id')
            ->exists();

        if ($hasGlobalAccess) {
            return $query->get();
        }

        $locationIds = UserDivisionAccess::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('user_id', $user->id)
            ->where('division_id', $scope['division_id'])
            ->where('profile', 'manager')
            ->where('active', true)
            ->whereNotNull('location_id')
            ->pluck('location_id');

        return $query->whereIn('id', $locationIds)->get();
    }

    private function canManageDivision(User $user, int $divisionId): bool
    {
        if ((int) $user->id === 1 || userHasProfile('admin')) {
            return Division::query()
                ->where('tenant_id', $user->tenant_id)
                ->whereKey($divisionId)
                ->exists();
        }

        return UserDivisionAccess::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('user_id', $user->id)
            ->where('division_id', $divisionId)
            ->where('profile', 'manager')
            ->where('active', true)
            ->exists();
    }

    private function canManageLocation(User $user, int $divisionId, int $locationId): bool
    {
        if ((int) $user->id === 1 || userHasProfile('admin')) {
            return true;
        }

        return UserDivisionAccess::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('user_id', $user->id)
            ->where('division_id', $divisionId)
            ->where('profile', 'manager')
            ->where('active', true)
            ->where(function ($query) use ($locationId) {
                $query
                    ->where('location_id', $locationId)
                    ->orWhereNull('location_id');
            })
            ->exists();
    }

    private function currentProfile(User $user): ?string
    {
        $divisionId = session('active_division_id');
        $locationId = session('active_location_id');

        if (! $divisionId) {
            return null;
        }

        return UserDivisionAccess::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('user_id', $user->id)
            ->where('division_id', $divisionId)
            ->where('module', 'fleet')
            ->where('active', true)
            ->where(function ($query) use ($locationId) {
                if ($locationId) {
                    $query
                        ->where('location_id', $locationId)
                        ->orWhereNull('location_id');

                    return;
                }

                $query->whereNull('location_id');
            })
            ->orderByRaw('location_id is null')
            ->value('profile');
    }
}