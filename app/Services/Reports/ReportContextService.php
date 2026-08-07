<?php

namespace App\Services\Reports;

use App\Models\Division;
use App\Models\MaintenanceRecord;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleUpdateLog;
use App\Services\ActiveContextService;
use App\Services\Permissions\ProfilePermissionService;
use Illuminate\Database\Eloquent\Builder;

class ReportContextService
{
    public function __construct(
        private readonly ActiveContextService $activeContext,
        private readonly ProfilePermissionService $permissions
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

        $permissionScope = [
            'division_id' => (int) $division->id,
            'location_id' => (int) $location->id,
            'module' => 'fleet',
        ];

        $canViewCancelled = $this->permissions->allows($user, 'reports.view_cancelled', $permissionScope);
        $canViewChanges = $this->permissions->allows($user, 'reports.view_changes', $permissionScope);
        $canViewAudit = $canViewCancelled
            && $this->permissions->allows($user, 'audit.view_details', $permissionScope);

        return [
            'user' => $user,
            'tenant_id' => (int) $user->tenant_id,
            'division' => $division,
            'location' => $location,
            'location_ids' => [(int) $location->id],
            'can_view_costs' => $this->permissions->allows($user, 'reports.view_costs', $permissionScope),
            'can_view_cancelled' => $canViewCancelled,
            'can_view_changes' => $canViewChanges,
            'can_view_audit' => $canViewAudit,
            'is_managerial_report' => $canViewCancelled || $canViewChanges || $canViewAudit,
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

}
