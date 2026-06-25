<?php

namespace App\Services\Reports;

use App\Models\Division;
use App\Models\MaintenanceRecord;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\UserDivisionAccess;
use App\Models\Vehicle;
use App\Models\VehicleUpdateLog;
use App\Services\ActiveContextService;
use Illuminate\Database\Eloquent\Builder;

class ReportContextService
{
    public function __construct(
        private readonly ActiveContextService $activeContext
    ) {
    }

    public function resolve(?User $user = null): ?array
    {
        $user ??= auth()->user();
        $divisionId = session('active_division_id');

        if (! $user || ! $divisionId) {
            return null;
        }

        $division = Division::query()
            ->where('tenant_id', $user->tenant_id)
            ->find($divisionId);

        $location = $this->activeContext->activeLocation($user);

        if (
            ! $division
            || ! $location
            || (int) $location->division_id !== (int) $division->id
            || (int) $location->tenant_id !== (int) $user->tenant_id
        ) {
            return null;
        }

        return [
            'user' => $user,
            'tenant_id' => (int) $user->tenant_id,
            'division' => $division,
            'location' => $location,
            'location_ids' => [(int) $location->id],
            'can_view_cancelled' => $this->canViewCancelled($user, (int) $division->id, (int) $location->id),
        ];
    }

    public function vehicleQuery(array $context): Builder
    {
        return Vehicle::query()
            ->where('tenant_id', $context['tenant_id'])
            ->where('division_id', $context['division']->id)
            ->where('location_id', $context['location']->id);
    }

    public function maintenanceQuery(array $context, bool $includeCancelled = false): Builder
    {
        $query = MaintenanceRecord::query()
            ->where('tenant_id', $context['tenant_id'])
            ->whereHas('vehicle', function (Builder $query) use ($context) {
                $query
                    ->where('tenant_id', $context['tenant_id'])
                    ->where('division_id', $context['division']->id)
                    ->where('location_id', $context['location']->id);
            });

        if (! $includeCancelled) {
            $query->whereNull('cancelled_at');
        }

        return $query;
    }

    public function stockMovementQuery(array $context): Builder
    {
        return StockMovement::query()
            ->where('tenant_id', $context['tenant_id'])
            ->where('location_id', $context['location']->id);
    }

    public function vehicleUpdateLogsQuery(array $context, int $vehicleId): Builder
    {
        return VehicleUpdateLog::query()
            ->where('vehicle_id', $vehicleId)
            ->where('division_id', $context['division']->id)
            ->where('location_id', $context['location']->id);
    }

    private function canViewCancelled(User $user, int $divisionId, int $locationId): bool
    {
        if ((int) $user->id === 1) {
            return true;
        }

        return UserDivisionAccess::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('user_id', $user->id)
            ->where('division_id', $divisionId)
            ->where('module', 'fleet')
            ->whereIn('profile', ['manager', 'admin'])
            ->where('active', true)
            ->where(function ($query) use ($locationId) {
                $query
                    ->where('location_id', $locationId)
                    ->orWhereNull('location_id');
            })
            ->exists();
    }
}
