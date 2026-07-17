<?php

namespace App\Http\Controllers;

use App\Services\AuditLogService;
use App\Services\Permissions\ProfilePermissionService;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    public function index(Request $request, ProfilePermissionService $permissions)
    {
        $this->authorizeAccess();

        $matrix = $permissions->matrix($request->user(), $request->query());

        return view('permissions.index', $matrix);
    }

    public function update(Request $request, ProfilePermissionService $permissions, AuditLogService $auditLog)
    {
        $this->authorizeAccess();

        $validated = $request->validate([
            'division_id' => ['required', 'integer'],
            'location_id' => ['nullable', 'integer'],
            'profile' => ['required', 'string'],
            'module' => ['required', 'string'],
            'permissions' => ['nullable', 'array'],
        ]);

        $before = $permissions->matrix($request->user(), $validated);

        $permissions->update($request->user(), $validated);

        $after = $permissions->matrix($request->user(), $validated);

        $auditLog->record([
            'module' => 'permissions',
            'action' => 'updated',
            'summary' => 'Atualizou permissões do perfil ' . ($validated['profile'] ?? '-'),
            'tenant_id' => $request->user()->tenant_id,
            'division_id' => $validated['division_id'] ?? null,
            'location_id' => $validated['location_id'] ?? null,
            'before_data' => [
                'scope' => $before['scope'],
                'groups' => $before['groups'],
            ],
            'after_data' => [
                'scope' => $after['scope'],
                'groups' => $after['groups'],
            ],
        ]);

        return redirect()
            ->route('permissions.index', [
                'division_id' => $after['scope']['division_id'],
                'location_id' => $after['scope']['location_id'],
                'profile' => $after['scope']['profile'],
                'module' => $after['scope']['module'],
            ])
            ->with('success', 'Permissões salvas com segurança.');
    }

    private function authorizeAccess(): void
    {
        $user = auth()->user();

        abort_unless(
            $user && ((int) $user->id === 1 || userHasProfile('admin') || userHasProfile('manager')),
            403
        );
    }
}