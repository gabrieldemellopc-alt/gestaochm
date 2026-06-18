<?php

namespace App\Http\Controllers;

use App\Models\SystemAuditLog;
use App\Models\User;
use App\Services\ActiveContextService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAuditLogs');

        $user = $request->user();
        $activeContext = app(ActiveContextService::class);
        $activeDivision = $activeContext->activeDivision($user);
        $activeLocation = $activeContext->activeLocation($user);

        $logsQuery = SystemAuditLog::query()
            ->with([
                'tenant:id,name',
                'division:id,name',
                'location:id,name',
                'user:id,name,email',
            ])
            ->where('tenant_id', $user->tenant_id)
            ->when($activeDivision, fn ($query) => $query->where('division_id', $activeDivision->id))
            ->when($activeLocation, fn ($query) => $query->where('location_id', $activeLocation->id));

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
            ->when($activeDivision, fn ($query) => $query->where('division_id', $activeDivision->id))
            ->when($activeLocation, fn ($query) => $query->where('location_id', $activeLocation->id));

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

        return view('audit.index', [
            'logs' => $logs,
            'modules' => $modules,
            'actions' => $actions,
            'auditUsers' => $auditUsers,
            'activeDivision' => $activeDivision,
            'activeLocation' => $activeLocation,
            'filters' => $request->only([
                'module',
                'action',
                'user_id',
                'date_from',
                'date_to',
                'search',
            ]),
        ]);
    }
}
