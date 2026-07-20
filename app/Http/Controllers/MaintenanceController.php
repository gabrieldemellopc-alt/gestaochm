<?php

namespace App\Http\Controllers;
use App\Models\Procedure;
use App\Models\MaintenanceRecord;
use App\Models\UserDivisionAccess;
use App\Models\Vehicle;
use App\Services\ActiveContextService;
use App\Services\Permissions\ProfilePermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use App\Services\MaintenanceService;
use Barryvdh\DomPDF\Facade\Pdf;

class MaintenanceController extends Controller
{
    public function store(Request $request, Vehicle $vehicle)
    {
        if ($redirect = $this->ensureVehicleInActiveContext($vehicle)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Selecione uma unidade para continuar.',
                ], 422);
            }

            return $redirect;
        }

        $this->authorizeMaintenancePermission('maintenance.open');

        $data = $request->validate([
            'started_at' => ['required', 'date'],
            'performed_km' => ['nullable', 'integer', 'min:0'],
            'performed_hours' => ['nullable', 'integer', 'min:0'],

            'reason' => ['nullable', 'in:preventive,corrective,inspection,other'],
            'extra_cost' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],

            'service_status' => [
                'nullable',
                'string',
                Rule::in(array_keys(MaintenanceService::serviceStatuses())),
            ],
            'maintenance_category' => [
                'nullable',
                'string',
                Rule::in(
                    array_keys(
                        MaintenanceService::maintenanceCategories()
                    )
                ),
            ],
        ]);

        $result = MaintenanceService::create($data, $vehicle);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Manutenção aberta com sucesso.',
                'maintenance' => $result['maintenance'],
            ], 201);
        }

        return redirect()
            ->route('vehicle.maintenance.index', $vehicle->id)
            ->with('success', 'Manutenção aberta com sucesso.');
    }

    public function cancel(
        Request $request,
        Vehicle $vehicle,
        MaintenanceRecord $maintenance
    ) {
        if ($redirect = $this->ensureVehicleInActiveContext($vehicle)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Selecione uma unidade para continuar.',
                ], 422);
            }

            return $redirect;
        }

        if ((int) $maintenance->vehicle_id !== (int) $vehicle->id) {
            abort(404);
        }

        if ((int) $maintenance->tenant_id !== (int) auth()->user()->tenant_id) {
            abort(403);
        }

        if (! ($this->userCanCancelMaintenance($vehicle) && $this->canMaintenance('maintenance.cancel'))) {
            abort(403, 'Voce nao tem permissao para executar esta acao.');
        }

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        $cancelled = MaintenanceService::cancel(
            $maintenance,
            $data['reason'],
            auth()->user()
        );

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Manutencao cancelada com sucesso.',
                'maintenance' => $cancelled,
            ]);
        }

        return back()->with('success', 'Manutencao cancelada com sucesso.');
    }


    public function changeStatus(
        Request $request,
        Vehicle $vehicle,
        MaintenanceRecord $maintenance
    ) {
        if ($redirect = $this->ensureVehicleInActiveContext($vehicle)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Selecione uma unidade para continuar.',
                ], 422);
            }

            return $redirect;
        }

        if ((int) $maintenance->vehicle_id !== (int) $vehicle->id) {
            abort(404);
        }

        if ((int) $maintenance->tenant_id !== (int) auth()->user()->tenant_id) {
            abort(403);
        }

        $this->authorizeMaintenancePermission('maintenance.change_status');

        $data = $request->validate([
            'service_status' => [
                'required',
                Rule::in(array_keys(MaintenanceService::serviceStatuses())),
            ],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        MaintenanceService::changeStatus(
            $maintenance,
            $data['service_status'],
            $data['reason'] ?? null
        );

        return back()->with('success', 'Status da manutenção atualizado.');
    }

    public function addItemCreate(
        Request $request,
        Vehicle $vehicle,
        MaintenanceRecord $maintenance
    ) {
        if ($redirect = $this->ensureVehicleInActiveContext($vehicle)) {
            return $redirect;
        }

        if ((int) $maintenance->vehicle_id !== (int) $vehicle->id) {
            abort(404);
        }

        $this->authorizeMaintenancePermission('maintenance.add_items');

        if ($maintenance->workflow_status !== 'open' || $maintenance->cancelled_at) {
            return redirect()
                ->route('vehicle.maintenance.index', $vehicle->id)
                ->withErrors([
                    'maintenance' => 'Somente manutenções abertas aceitam novos procedimentos.',
                ]);
        }

        $data = $request->validate([
            'procedure_id' => [
                'required',
                Rule::exists('procedures', 'id')
                    ->where(fn ($query) => $query
                        ->where('tenant_id', $vehicle->tenant_id)
                        ->where('location_id', $vehicle->location_id)),
            ],
            'execution_type' => ['required', 'in:internal,external'],
        ]);

        $vehicle->load(['division', 'location']);

        $procedure = Procedure::with([
            'fields.stockCategory.items' => function ($query) use ($vehicle) {
                $query
                    ->where('tenant_id', $vehicle->tenant_id)
                    ->where('location_id', $vehicle->location_id)
                    ->where('active', true);
            },
        ])
            ->where('tenant_id', $vehicle->tenant_id)
            ->where('location_id', $vehicle->location_id)
            ->findOrFail($data['procedure_id']);

        if (
            $data['execution_type'] === 'internal'
            && ! $procedure->can_be_internal
        ) {
            return redirect()
                ->route('vehicle.maintenance.index', $vehicle->id)
                ->withErrors([
                    'execution_type' => 'Este procedimento não permite execução em oficina interna.',
                ]);
        }

        return view('vehicle.maintenance-add-item', [
            'vehicle' => $vehicle,
            'maintenance' => $maintenance,
            'procedure' => $procedure,
            'executionType' => $data['execution_type'],
        ]);
    }

    public function storeItem(
        Request $request,
        Vehicle $vehicle,
        MaintenanceRecord $maintenance
    ) {
        if ($redirect = $this->ensureVehicleInActiveContext($vehicle)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Selecione uma unidade para continuar.',
                ], 422);
            }

            return $redirect;
        }

        if ((int) $maintenance->vehicle_id !== (int) $vehicle->id) {
            abort(404);
        }

        $this->authorizeMaintenancePermission('maintenance.add_items');

        $data = $request->validate([
            'procedure_id' => [
                'required',
                Rule::exists('procedures', 'id')
                    ->where(fn ($query) => $query
                        ->where('tenant_id', $vehicle->tenant_id)
                        ->where('location_id', $vehicle->location_id)),
            ],
            'maintenance_type' => ['required', 'in:internal,external'],

            'performed_at' => ['required', 'date'],
            'performed_km' => ['nullable', 'integer', 'min:0'],
            'performed_hours' => ['nullable', 'integer', 'min:0'],

            'extra_cost' => ['nullable', 'numeric', 'min:0'],
            'provider_name' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],

            'fields' => ['nullable', 'array'],
        ]);

        if ($this->procedureUsesStock($data['procedure_id'], $data['fields'] ?? [])) {
            $this->authorizeMaintenancePermission('maintenance.consume_stock');
        }

        $item = MaintenanceService::addItem($maintenance, $data);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Procedimento adicionado à manutenção.',
                'item' => $item,
            ], 201);
        }

        return redirect()
            ->route('vehicle.maintenance.index', $vehicle->id)
            ->with('success', 'Procedimento adicionado à manutenção.');
    }

    public function show(
        Vehicle $vehicle,
        MaintenanceRecord $maintenance
    ) {
        if ($redirect = $this->ensureVehicleInActiveContext($vehicle)) {
            return $redirect;
        }

        if ((int) $maintenance->vehicle_id !== (int) $vehicle->id) {
            abort(404);
        }

        $this->authorizeMaintenancePermission('maintenance.view');

        if ((int) $maintenance->tenant_id !== (int) auth()->user()->tenant_id) {
            abort(403);
        }

        if ($maintenance->deleted_at) {
            abort(404);
        }

        $maintenance->load([
            'vehicle.division',
            'vehicle.location',
            'procedure',
            'items.procedure',
            'items.values.field',
            'extraCosts.creator',
            'statusLogs.user',
            'opener',
            'closer',
            'canceller',
            'deleter',
        ]);

        $maintenancePermissions = $this->maintenancePermissions($vehicle);
        $canManageMaintenance = $maintenancePermissions['cancel'];

        return view('vehicle.maintenance-show', compact(
            'vehicle',
            'maintenance',
            'canManageMaintenance',
            'maintenancePermissions'
        ));
    }

    public function reopen(
        Request $request,
        Vehicle $vehicle,
        MaintenanceRecord $maintenance
    ) {
        if ($redirect = $this->ensureVehicleInActiveContext($vehicle)) {
            return $redirect;
        }

        if ((int) $maintenance->vehicle_id !== (int) $vehicle->id) {
            abort(404);
        }

        if ((int) $maintenance->tenant_id !== (int) auth()->user()->tenant_id) {
            abort(403);
        }

        $this->authorizeMaintenancePermission('maintenance.reopen');

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        MaintenanceService::reopen(
            $maintenance,
            $data['reason'],
            auth()->user()
        );

        return redirect()
            ->route('vehicle.maintenance.index', $vehicle->id)
            ->with('success', 'Manutenção reaberta com sucesso.');
    }

    public function destroy(
        Request $request,
        Vehicle $vehicle,
        MaintenanceRecord $maintenance
    ) {
        if ($redirect = $this->ensureVehicleInActiveContext($vehicle)) {
            return $redirect;
        }

        if ((int) $maintenance->vehicle_id !== (int) $vehicle->id) {
            abort(404);
        }

        if ((int) $maintenance->tenant_id !== (int) auth()->user()->tenant_id) {
            abort(403);
        }

        $this->authorizeMaintenancePermission('maintenance.delete');

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        MaintenanceService::logicalDelete(
            $maintenance,
            $data['reason'],
            auth()->user()
        );

        return redirect()
            ->route('vehicle.maintenance.index', $vehicle->id)
            ->with('success', 'Manutenção apagada com sucesso.');
    }

    private function ensureVehicleInActiveContext(Vehicle $vehicle)
    {
        $activeLocation = app(ActiveContextService::class)
            ->activeLocation(auth()->user());

        if (! $activeLocation) {
            return redirect()
                ->route('portal')
                ->with(
                    'warning',
                    'Selecione uma unidade para continuar.'
                );
        }

        if (
            (int) $vehicle->tenant_id !== (int) auth()->user()->tenant_id
            || (int) $vehicle->division_id !== (int) session('active_division_id')
            || (int) $vehicle->location_id !== (int) $activeLocation->id
        ) {
            abort(403);
        }

        return null;
    }

    private function authorizeMaintenancePermission(string $permissionKey): void
    {
        if ($this->canMaintenance($permissionKey)) {
            return;
        }

        abort(403, 'Voce nao tem permissao para executar esta acao.');
    }

    private function canMaintenance(string $permissionKey): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return app(ProfilePermissionService::class)->allows($user, $permissionKey, [
            'tenant_id' => $user->tenant_id,
            'division_id' => session('active_division_id'),
            'location_id' => session('active_location_id'),
            'module' => 'fleet',
        ]);
    }

    private function maintenancePermissions(?Vehicle $vehicle = null): array
    {
        return [
            'view' => $this->canMaintenance('maintenance.view'),
            'open' => $this->canMaintenance('maintenance.open'),
            'add_items' => $this->canMaintenance('maintenance.add_items'),
            'consume_stock' => $this->canMaintenance('maintenance.consume_stock'),
            'add_extra_costs' => $this->canMaintenance('maintenance.add_extra_costs'),
            'change_status' => $this->canMaintenance('maintenance.change_status'),
            'close' => $this->canMaintenance('maintenance.close'),
            'cancel' => $this->userCanCancelMaintenance($vehicle) && $this->canMaintenance('maintenance.cancel'),
            'reopen' => $this->canMaintenance('maintenance.reopen'),
            'delete' => $this->canMaintenance('maintenance.delete'),
            'view_costs' => $this->canMaintenance('maintenance.view_costs'),
            'export_pdf' => $this->canMaintenance('maintenance.export_pdf'),
            'view_cancellation_details' => Gate::allows('viewAuditLogs')
                && $this->canMaintenance('maintenance.view_cancellation_details'),
        ];
    }

    private function procedureUsesStock(int $procedureId, array $fields): bool
    {
        if ($fields === []) {
            return false;
        }

        $stockFields = Procedure::query()
            ->where('tenant_id', auth()->user()->tenant_id)
            ->where('id', $procedureId)
            ->with('fields')
            ->first()
            ?->fields
            ->where('field_type', 'stock_item')
            ->pluck('slug')
            ->all() ?? [];

        foreach ($stockFields as $slug) {
            if (! empty($fields[$slug])) {
                return true;
            }
        }

        return false;
    }
    private function userCanCancelMaintenance(?Vehicle $vehicle = null): bool
    {
        $divisionId = $vehicle?->division_id ?? session('active_division_id');
        $locationId = $vehicle?->location_id ?? session('active_location_id');

        if (! $divisionId || ! $locationId) {
            return false;
        }

        return UserDivisionAccess::query()
            ->where('tenant_id', auth()->user()->tenant_id)
            ->where('user_id', auth()->id())
            ->where('division_id', $divisionId)
            ->where('module', 'fleet')
            ->whereIn('profile', ['supervisor', 'manager', 'admin'])
            ->where('active', true)
            ->where(function ($query) use ($locationId) {
                $query
                    ->where('location_id', $locationId)
                    ->orWhereNull('location_id');
            })
            ->exists();
    }

    public function close(
        Request $request,
        Vehicle $vehicle,
        MaintenanceRecord $maintenance
    ) {


        if ($redirect = $this->ensureVehicleInActiveContext($vehicle)) {
            return $redirect;
        }

        if ((int) $maintenance->vehicle_id !== (int) $vehicle->id) {
            abort(404);
        }

        $this->authorizeMaintenancePermission('maintenance.close');

        $data = $request->validate([
            'vehicle_status_after' => [
                'required',
                'in:operational,inactive,inoperant,accident,support,testing,transfer,transferred',
            ],
            'closure_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        MaintenanceService::close(
            $maintenance,
            $data['vehicle_status_after'],
            $data['closure_notes'] ?? null
        );

        return redirect()
            ->route('vehicle.maintenance.index', $vehicle->id)
            ->with('success', 'Manutenção encerrada com sucesso.');
    }

    public function storeExtraCost(
        Request $request,
        Vehicle $vehicle,
        MaintenanceRecord $maintenance
    ) {
        if ($redirect = $this->ensureVehicleInActiveContext($vehicle)) {
            return $redirect;
        }

        if ((int) $maintenance->vehicle_id !== (int) $vehicle->id) {
            abort(404);
        }

        $this->authorizeMaintenancePermission('maintenance.add_extra_costs');

        $data = $request->validate([
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
        ]);

        MaintenanceService::addExtraCost($maintenance, $data);

        return back()->with('success', 'Custo avulso lançado com sucesso.');
    }

    public function exportOrderPdf(
        Vehicle $vehicle,
        MaintenanceRecord $maintenance
    ) {
        if ($redirect = $this->ensureVehicleInActiveContext($vehicle)) {
            return $redirect;
        }

        if ((int) $maintenance->vehicle_id !== (int) $vehicle->id) {
            abort(404);
        }

        $this->authorizeMaintenancePermission('maintenance.export_pdf');

        $maintenance->load([
            'vehicle.division',
            'vehicle.location',
            'items.procedure',
            'items.values.field',
            'extraCosts.creator',
            'statusLogs.user',
            'opener',
            'closer',
            'canceller',
        ]);

        $pdf = Pdf::loadView('vehicle.pdf.maintenance-order', [
            'vehicle' => $vehicle,
            'maintenance' => $maintenance,
            'canViewAuditLogs' => $this->maintenancePermissions($vehicle)['view_cancellation_details'],
        ])->setPaper('a4', 'portrait');

        return $pdf->download(
            'ordem-manutencao-'.$maintenance->id.'.pdf'
        );
    }

}
