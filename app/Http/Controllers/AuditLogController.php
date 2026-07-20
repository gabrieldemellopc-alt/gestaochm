<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\SystemAuditLog;
use App\Models\User;
use App\Models\UserDivisionAccess;
use App\Services\ActiveContextService;
use App\Services\Permissions\ProfilePermissionService;
use App\Support\AuditLogPresenter;
use App\Support\ChmLabel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAuditLogs');
        $this->authorizeAuditPermission('audit.view');

        $user = $request->user();
        $activeContext = app(ActiveContextService::class);
        $activeDivision = $activeContext->activeDivision($user);

        if (! $activeDivision) {
            return redirect()
                ->route('portal')
                ->with('warning', 'Selecione uma divisao para consultar a auditoria.');
        }

        $auditLocations = $this->auditLocationsFor($user, $activeDivision->id);
        $selectedLocationId = $request->filled('location_id')
            ? $request->integer('location_id')
            : null;

        if (
            $selectedLocationId
            && ! $auditLocations->contains('id', $selectedLocationId)
        ) {
            abort(403);
        }

        $auditScopeLabel = $selectedLocationId
            ? $auditLocations->firstWhere('id', $selectedLocationId)?->name
            : 'Todas as unidades permitidas';

        $logsQuery = SystemAuditLog::query()
            ->with([
                'tenant:id,name',
                'division:id,name',
                'location:id,name',
                'user:id,name,email',
            ])
            ->where('tenant_id', $user->tenant_id)
            ->where('division_id', $activeDivision->id)
            ->when(
                $selectedLocationId,
                fn ($query) => $query->where('location_id', $selectedLocationId),
                fn ($query) => $this->applyAuditLocationScope($query, $auditLocations)
            );

        $logsQuery
            ->when($request->filled('module'), fn ($query) => $query->where('module', $request->input('module')))
            ->when($request->filled('action'), fn ($query) => $query->where('action', $request->input('action')))
            ->when($request->filled('user_id'), fn ($query) => $query->where('user_id', $request->integer('user_id')))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('created_at', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('created_at', '<=', $request->date('date_to')))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim((string) $request->input('search'));

                $query->where(function ($query) use ($search) {
                    $query
                        ->where('summary', 'like', "%{$search}%")
                        ->orWhere('reason', 'like', "%{$search}%")
                        ->orWhere('auditable_type', 'like', "%{$search}%")
                        ->orWhere('auditable_id', 'like', "%{$search}%");
                });
            });

        $logs = (clone $logsQuery)
            ->latest('created_at')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $baseOptionsQuery = SystemAuditLog::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('division_id', $activeDivision->id)
            ->when(
                $selectedLocationId,
                fn ($query) => $query->where('location_id', $selectedLocationId),
                fn ($query) => $this->applyAuditLocationScope($query, $auditLocations)
            );

        $modules = (clone $baseOptionsQuery)
            ->whereNotNull('module')
            ->distinct()
            ->orderBy('module')
            ->pluck('module');

        $actions = (clone $baseOptionsQuery)
            ->whereNotNull('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action');

        $auditUsers = User::query()
            ->whereIn(
                'id',
                (clone $baseOptionsQuery)
                    ->whereNotNull('user_id')
                    ->distinct()
                    ->pluck('user_id')
            )
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $presenter = app(AuditLogPresenter::class);
        $auditEvents = $logs
            ->getCollection()
            ->map(fn (SystemAuditLog $log) => $presenter->present($log));
        return view('audit.index', [
            'logs' => $logs,
            'auditEvents' => $auditEvents,
            'modules' => $modules,
            'actions' => $actions,
            'auditUsers' => $auditUsers,
            'activeDivision' => $activeDivision,
            'auditLocations' => $auditLocations,
            'selectedLocationId' => $selectedLocationId,
            'auditScopeLabel' => $auditScopeLabel,
            'filters' => $request->only([
                'module',
                'action',
                'user_id',
                'location_id',
                'date_from',
                'date_to',
                'search',
            ]),
            'moduleLabels' => $modules
                ->mapWithKeys(fn ($module) => [$module => ChmLabel::for('audit_module', $module)])
                ->all(),
            'auditPermissions' => $this->auditPermissions(),
            'actionLabels' => $actions
                ->mapWithKeys(fn ($action) => [$action => ChmLabel::for('audit_action', $action)])
                ->all(),
        ]);
    }


    private function authorizeAuditPermission(string $permissionKey): void
    {
        abort_unless($this->canAuditPermission($permissionKey), 403);
    }

    private function canAuditPermission(string $permissionKey): bool
    {
        return app(ProfilePermissionService::class)->allows(request()->user(), $permissionKey, [
            'module' => 'fleet',
        ]);
    }

    private function auditPermissions(): array
    {
        $keys = [
            'audit.view',
            'audit.view_details',
            'audit.view_technical_details',
        ];

        return collect($keys)
            ->mapWithKeys(fn (string $key) => [$key => $this->canAuditPermission($key)])
            ->all();
    }
    private function auditLocationsFor(User $user, int $divisionId)
    {
        $accesses = UserDivisionAccess::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('user_id', $user->id)
            ->where('division_id', $divisionId)
            ->where('module', 'fleet')
            ->whereIn('profile', ['manager', 'admin'])
            ->where('active', true)
            ->get(['location_id']);

        if ($accesses->contains(fn ($access) => $access->location_id === null)) {
            return Location::query()
                ->where('tenant_id', $user->tenant_id)
                ->where('division_id', $divisionId)
                ->orderBy('name')
                ->get(['id', 'name']);
        }

        return Location::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('division_id', $divisionId)
            ->whereIn(
                'id',
                $accesses->pluck('location_id')->filter()->unique()->values()
            )
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function applyAuditLocationScope($query, $auditLocations): void
    {
        $locationIds = $auditLocations->pluck('id')->values();

        $query->where(function ($query) use ($locationIds) {
            $query->whereNull('location_id');

            if ($locationIds->isNotEmpty()) {
                $query->orWhereIn('location_id', $locationIds);
            }
        });
    }
}
