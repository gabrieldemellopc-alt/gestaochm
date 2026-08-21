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

        $validated = $this->validatedScope($request) + [
            'permissions' => $request->input('permissions', []),
        ];

        $before = $permissions->matrix($request->user(), $validated);

        $permissions->update($request->user(), $validated);

        $after = $permissions->matrix($request->user(), $validated);

        $this->auditPermissionAction($request, $auditLog, 'updated', 'Atualizou permissões do perfil ' . ($validated['profile'] ?? '-'), $before, $after);

        return $this->redirectToScope($after['scope'])
            ->with('success', 'Permissões salvas com segurança.');
    }

    public function reset(Request $request, ProfilePermissionService $permissions, AuditLogService $auditLog)
    {
        $this->authorizeAccess();

        $validated = $this->validatedScope($request);
        $before = $permissions->matrix($request->user(), $validated);
        $result = $permissions->resetOverrides($request->user(), $validated);
        $after = $permissions->matrix($request->user(), $result['scope']);

        $this->auditPermissionAction($request, $auditLog, 'reset', 'Restaurou o padrão de permissões do perfil ' . ($validated['profile'] ?? '-'), $before, $after);

        return $this->redirectToScope($result['scope'])
            ->with('success', 'Padrão restaurado para o escopo selecionado. Overrides removidos: ' . $result['deleted'] . '.');
    }

    public function applyToDivision(Request $request, ProfilePermissionService $permissions, AuditLogService $auditLog)
    {
        $this->authorizeAccess();

        $validated = $this->validatedScope($request);
        $before = $permissions->matrix($request->user(), $validated);
        $result = $permissions->applyCurrentMatrixToDivisionLocations($request->user(), $validated);
        $after = $permissions->matrix($request->user(), $result['scope']);

        $this->auditPermissionAction($request, $auditLog, 'applied_to_division', 'Aplicou permissões para unidades da divisão', $before, $after);

        return $this->redirectToScope($result['scope'])
            ->with('success', 'Permissões aplicadas para ' . $result['locations_count'] . ' unidade(s) da divisão.');
    }

    public function copyFromLocation(Request $request, ProfilePermissionService $permissions, AuditLogService $auditLog)
    {
        $this->authorizeAccess();

        $validated = $this->validatedScope($request) + $request->validate([
            'source_location_id' => ['required', 'integer'],
        ]);

        $before = $permissions->matrix($request->user(), $validated);
        $result = $permissions->copyOverridesFromLocation($request->user(), $validated);
        $after = $permissions->matrix($request->user(), $result['scope']);

        $this->auditPermissionAction($request, $auditLog, 'copied_from_location', 'Copiou permissões de outra unidade', $before, $after);

        return $this->redirectToScope($result['scope'])
            ->with('success', 'Permissões copiadas da unidade selecionada.');
    }

    private function validatedScope(Request $request): array
    {
        return $request->validate([
            'division_id' => ['required', 'integer'],
            'location_id' => ['nullable', 'integer'],
            'profile' => ['required', 'string'],
            'module' => ['required', 'string'],
        ]);
    }

    private function redirectToScope(array $scope)
    {
        return redirect()->route('permissions.index', [
            'division_id' => $scope['division_id'],
            'location_id' => $scope['location_id'],
            'profile' => $scope['profile'],
            'module' => $scope['module'],
        ]);
    }

    private function auditPermissionAction(Request $request, AuditLogService $auditLog, string $action, string $summary, array $before, array $after): void
    {
        $auditLog->record([
            'module' => 'permissions',
            'action' => $action,
            'summary' => $summary,
            'tenant_id' => $request->user()->tenant_id,
            'division_id' => $after['scope']['division_id'] ?? null,
            'location_id' => $after['scope']['location_id'] ?? null,
            'before_data' => [
                'scope' => $before['scope'],
                'groups' => $before['groups'],
            ],
            'after_data' => [
                'scope' => $after['scope'],
                'groups' => $after['groups'],
            ],
        ]);
    }

    private function authorizeAccess(): void
    {
        $user = auth()->user();

        abort_unless(app(ProfilePermissionService::class)->allows($user, 'admin.permissions.configure'), 403);
    }
}
