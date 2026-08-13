<?php

namespace App\Http\Controllers;
use App\Models\Procedure;
use App\Models\MaintenanceRecord;
use App\Models\MaintenanceRecordExtraCost;
use App\Models\MaintenanceRecordItem;
use App\Models\MaintenanceMaterialUsage;
use App\Models\UserDivisionAccess;
use App\Models\Vehicle;
use App\Services\ActiveContextService;
use App\Services\Permissions\ProfilePermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use App\Services\MaintenanceService;
use App\Services\MaintenanceMaterialService;
use Barryvdh\DomPDF\Facade\Pdf;

class MaintenanceController extends Controller
{
    public function searchMaterials(Request $request, Vehicle $vehicle, MaintenanceRecord $maintenance, MaintenanceMaterialService $service)
    {
        if ($redirect = $this->ensureVehicleInActiveContext($vehicle)) { return $redirect; }
        $this->assertMaintenanceRelation($vehicle, $maintenance);
        $this->authorizeMaintenancePermission('maintenance.use_materials');
        $items = $service->search($maintenance->loadMissing('vehicle'), (string) $request->query('q', ''));
        $showCosts = $this->canMaintenance('maintenance.view_costs');
        return response()->json($items->map(fn ($item) => [
            'id' => $item->id, 'name' => $item->name, 'brand' => $item->brand,
            'category' => $item->category?->name, 'unit' => $item->unit,
            'available_quantity' => (float) $item->quantity,
            'unit_cost' => $showCosts ? (float) $item->unit_cost : null,
        ]));
    }

    public function storeMaterial(Request $request, Vehicle $vehicle, MaintenanceRecord $maintenance, MaintenanceMaterialService $service)
    {
        if ($redirect = $this->ensureVehicleInActiveContext($vehicle)) { return $redirect; }
        $this->assertMaintenanceRelation($vehicle, $maintenance);
        $this->authorizeMaintenancePermission('maintenance.use_materials');
        $data = $request->validate([
            'stock_item_id' => ['required', 'integer'], 'quantity' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $material = $service->add($maintenance, $data, auth()->user());
        if ($request->expectsJson()) {
            return response()->json($this->materialPayload($vehicle, $maintenance, 'Material lançado com sucesso.', $material));
        }
        return back()->with('success', 'Material adicionado com sucesso.');
    }

    public function cancelMaterial(Request $request, Vehicle $vehicle, MaintenanceRecord $maintenance, MaintenanceMaterialUsage $usage, MaintenanceMaterialService $service)
    {
        if ($redirect = $this->ensureVehicleInActiveContext($vehicle)) { return $redirect; }
        $this->assertMaintenanceRelation($vehicle, $maintenance);
        abort_unless((int) $usage->maintenance_record_id === (int) $maintenance->id, 404);
        $this->authorizeMaintenancePermission('maintenance.cancel_materials');
        $data = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:2000']]);
        $service->cancel($maintenance, $usage, $data['reason'], auth()->user());
        if ($request->expectsJson()) {
            return response()->json($this->materialPayload($vehicle, $maintenance, 'Material cancelado e devolvido ao estoque.'));
        }
        return back()->with('success', 'Material cancelado e devolvido ao estoque.');
    }

    public function replaceMaterial(Request $request, Vehicle $vehicle, MaintenanceRecord $maintenance, MaintenanceMaterialUsage $usage, MaintenanceMaterialService $service)
    {
        if ($redirect = $this->ensureVehicleInActiveContext($vehicle)) { return $redirect; }
        $this->assertMaintenanceRelation($vehicle, $maintenance);
        abort_unless((int) $usage->maintenance_record_id === (int) $maintenance->id, 404);
        $this->authorizeMaintenancePermission('maintenance.cancel_materials');
        $this->authorizeMaintenancePermission('maintenance.use_materials');
        $data = $request->validate([
            'stock_item_id' => ['required', 'integer'], 'quantity' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'change_reason' => ['required', 'string', 'min:10', 'max:2000'],
        ]);
        $material = $service->replace($maintenance, $usage, $data, auth()->user());
        if ($request->expectsJson()) {
            return response()->json($this->materialPayload($vehicle, $maintenance, 'Material corrigido com devolução e nova baixa.', $material));
        }
        return back()->with('success', 'Material corrigido com devolução e nova baixa.');
    }

    private function materialPayload(Vehicle $vehicle, MaintenanceRecord $maintenance, string $message, ?MaintenanceMaterialUsage $material = null): array
    {
        $maintenance = $maintenance->fresh([
            'materialUsages.stockItem.category',
            'materialUsages.creator',
            'procedureMaterialMovements.stockItem.category',
            'procedureMaterialMovements.maintenanceRecordItem.procedure',
        ]);
        $canViewCosts = $this->canMaintenance('maintenance.view_costs');
        $canCancelMaterials = $this->canMaintenance('maintenance.cancel_materials');

        return [
            'message' => $message,
            'material' => $material ? [
                'id' => $material->id,
                'stock_item_id' => $material->stock_item_id,
                'quantity' => (float) $material->quantity,
                'notes' => $material->notes,
                'unit_cost' => $canViewCosts ? (float) $material->unit_cost : null,
                'total_cost' => $canViewCosts ? (float) $material->total_cost : null,
            ] : null,
            'count' => $maintenance->materialUsages->count(),
            'quantity_total' => (float) $maintenance->materialUsages->sum('quantity'),
            'materials_total' => $canViewCosts ? (float) $maintenance->materialUsages->sum('total_cost') : null,
            'maintenance_total' => $canViewCosts ? (float) $maintenance->total_cost : null,
            'list_html' => view('vehicle.partials.maintenance-materials-list', [
                'vehicle' => $vehicle,
                'maintenance' => $maintenance,
                'canViewCosts' => $canViewCosts,
                'canCancelMaterials' => $canCancelMaterials,
            ])->render(),
        ];
    }

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
            'km_reading_confirmed' => ['nullable', 'boolean'],
            'hours_reading_confirmed' => ['nullable', 'boolean'],

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
        ], [
            'reason.required' => 'Informe o motivo do cancelamento.',
            'reason.min' => 'Informe um motivo com pelo menos :min caracteres.',
            'reason.max' => 'O motivo do cancelamento não pode ter mais que :max caracteres.',
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

        $replacementItem = null;
        if ($request->filled('replace_item')) {
            $this->authorizeMaintenancePermission('maintenance.edit_items');
            $replacementItem = MaintenanceRecordItem::query()
                ->with(['procedure', 'stockMovements.stockItem'])
                ->where('maintenance_record_id', $maintenance->id)
                ->whereNull('cancelled_at')
                ->findOrFail($request->integer('replace_item'));

            if ($replacementItem->stockMovements()
                ->where('movement_type', 'out')
                ->whereNull('reversal_movement_id')
                ->exists()) {
                $this->authorizeMaintenancePermission('maintenance.consume_stock');
            }
        } else {
            $this->authorizeMaintenancePermission('maintenance.add_items');
        }

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
            'replace_item' => ['nullable', 'integer'],
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

        $visibleProcedureFields = $procedure->fields->filter(
            fn ($field) => in_array($field->field_type, ['text', 'number'], true)
                || ($field->field_type === 'stock_item' && $data['execution_type'] === 'internal')
        );

        return view('vehicle.maintenance-add-item', [
            'vehicle' => $vehicle,
            'maintenance' => $maintenance,
            'procedure' => $procedure,
            'visibleProcedureFields' => $visibleProcedureFields,
            'executionType' => $data['execution_type'],
            'replacementItem' => $replacementItem,
            'canViewCosts' => $this->canMaintenance('maintenance.view_costs'),
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
            'reason' => ['required', 'in:preventive,corrective,inspection,other'],

            'performed_at' => ['required', 'date'],
            'performed_km' => ['nullable', 'integer', 'min:0'],
            'performed_hours' => ['nullable', 'integer', 'min:0'],

            'extra_cost' => ['nullable', 'numeric', 'min:0'],
            'provider_name' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],

            'fields' => ['nullable', 'array'],
        ], [
            'reason.required' => 'Selecione o motivo da manutenção.',
            'reason.in' => 'Selecione um motivo de manutenção válido.',
        ]);

        if (! $this->canMaintenance('maintenance.view_costs')) {
            $data['extra_cost'] = (float) $item->extra_cost;
        }

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

    public function updateItem(
        Request $request,
        Vehicle $vehicle,
        MaintenanceRecord $maintenance,
        MaintenanceRecordItem $item
    ) {
        if ($redirect = $this->ensureVehicleInActiveContext($vehicle)) {
            return $redirect;
        }

        $this->assertMaintenanceRelation($vehicle, $maintenance);
        abort_unless((int) $item->maintenance_record_id === (int) $maintenance->id, 404);
        $this->authorizeMaintenancePermission('maintenance.edit_items');

        $rules = [
            'maintenance_type' => ['required', 'in:internal,external'],
            'performed_at' => ['required', 'date'],
            'provider_name' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'change_reason' => ['required', 'string', 'min:10', 'max:2000'],
            'procedure_id' => ['prohibited'],
            'performed_km' => ['prohibited'],
            'performed_hours' => ['prohibited'],
            'reason' => ['prohibited'],
            'fields' => ['prohibited'],
            'stock_item_id' => ['prohibited'],
            'quantity' => ['prohibited'],
        ];

        if ($this->canMaintenance('maintenance.view_costs')) {
            $rules['extra_cost'] = ['required', 'numeric', 'min:0'];
        }

        $data = $request->validate($rules, [
            'change_reason.required' => 'Informe o motivo da alteração.',
            'change_reason.min' => 'O motivo da alteração deve ter pelo menos :min caracteres.',
            'extra_cost.min' => 'O custo do serviço não pode ser negativo.',
            '*.prohibited' => 'Este dado não pode ser alterado pela edição simples do serviço.',
        ]);

        MaintenanceService::updateItem($maintenance, $item, $data, auth()->user());

        return back()->with('success', 'Serviço atualizado com sucesso.');
    }

    public function replaceItem(
        Request $request,
        Vehicle $vehicle,
        MaintenanceRecord $maintenance,
        MaintenanceRecordItem $item
    ) {
        if ($redirect = $this->ensureVehicleInActiveContext($vehicle)) {
            return $redirect;
        }

        $this->assertMaintenanceRelation($vehicle, $maintenance);
        abort_unless((int) $item->maintenance_record_id === (int) $maintenance->id, 404);
        $this->authorizeMaintenancePermission('maintenance.edit_items');

        if ($item->stockMovements()
            ->where('movement_type', 'out')
            ->whereNull('reversal_movement_id')
            ->exists()) {
            $this->authorizeMaintenancePermission('maintenance.consume_stock');
        }

        $data = $request->validate([
            'procedure_id' => [
                'required',
                Rule::exists('procedures', 'id')->where(fn ($query) => $query
                    ->where('tenant_id', $vehicle->tenant_id)
                    ->where('location_id', $vehicle->location_id)),
            ],
            'maintenance_type' => ['required', 'in:internal,external'],
            'reason' => ['required', 'in:preventive,corrective,inspection,other'],
            'performed_at' => ['required', 'date'],
            'performed_km' => ['nullable', 'integer', 'min:0'],
            'performed_hours' => ['nullable', 'integer', 'min:0'],
            'extra_cost' => ['nullable', 'numeric', 'min:0'],
            'provider_name' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'fields' => ['nullable', 'array'],
            'change_reason' => ['required', 'string', 'min:10', 'max:2000'],
            'confirm_replacement' => ['accepted'],
        ], [
            'change_reason.required' => 'Informe o motivo da substituição.',
            'change_reason.min' => 'O motivo da substituição deve ter pelo menos :min caracteres.',
            'confirm_replacement.accepted' => 'Confirme que o lançamento atual será substituído.',
            'reason.required' => 'Selecione o motivo da manutenção.',
            'reason.in' => 'Selecione um motivo de manutenção válido.',
        ]);

        if ($this->procedureUsesStock($data['procedure_id'], $data['fields'] ?? [])) {
            $this->authorizeMaintenancePermission('maintenance.consume_stock');
        }

        MaintenanceService::replaceItem($maintenance, $item, $data, auth()->user());

        return redirect()
            ->route('vehicle.maintenance.index', $vehicle->id)
            ->with('success', 'Serviço substituído com sucesso.');
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
            'materialUsages.stockItem.category',
            'materialUsages.creator',
            'allMaterialUsages.stockItem.category',
            'allMaterialUsages.creator',
            'allMaterialUsages.canceller',
            'allMaterialUsages.replacement.stockItem.category',
            'statusLogs.user',
            'opener',
            'closer',
            'canceller',
            'deleter',
        ]);

        $maintenancePermissions = $this->maintenancePermissions($vehicle);
        $canManageMaintenance = $maintenancePermissions['cancel'];
        $canEditItems = $maintenancePermissions['edit_items'];
        $canEditExtraCosts = $maintenancePermissions['edit_extra_costs'];
        $canViewCosts = $maintenancePermissions['view_costs'];

        return view('vehicle.maintenance-show', compact(
            'vehicle',
            'maintenance',
            'canManageMaintenance',
            'maintenancePermissions',
            'canEditItems',
            'canEditExtraCosts',
            'canViewCosts'
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

    private function assertMaintenanceRelation(Vehicle $vehicle, MaintenanceRecord $maintenance): void
    {
        abort_unless((int) $maintenance->vehicle_id === (int) $vehicle->id, 404);
        abort_unless((int) $maintenance->tenant_id === (int) auth()->user()->tenant_id, 403);
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
        $user = auth()->user();
        $scope = [
            'tenant_id' => $user->tenant_id,
            'division_id' => $vehicle?->division_id ?? session('active_division_id'),
            'location_id' => $vehicle?->location_id ?? session('active_location_id'),
            'module' => 'fleet',
        ];
        $can = fn (string $permission) => app(ProfilePermissionService::class)
            ->allows($user, $permission, $scope);

        return [
            'view' => $can('maintenance.view'),
            'open' => $can('maintenance.open'),
            'add_items' => $can('maintenance.add_items'),
            'edit_items' => $can('maintenance.edit_items'),
            'consume_stock' => $can('maintenance.consume_stock'),
            'add_extra_costs' => $can('maintenance.add_extra_costs'),
            'use_materials' => $can('maintenance.use_materials'),
            'cancel_materials' => $can('maintenance.cancel_materials'),
            'edit_extra_costs' => $can('maintenance.edit_extra_costs'),
            'change_status' => $can('maintenance.change_status'),
            'close' => $can('maintenance.close'),
            'upload_photos' => $can('maintenance.upload_photos'),
            'delete_photos' => $can('maintenance.delete_photos'),
            'generate_photo_qr' => $can('maintenance.generate_photo_qr'),
            'cancel' => $this->userCanCancelMaintenance($vehicle) && $can('maintenance.cancel'),
            'reopen' => $can('maintenance.reopen'),
            'delete' => $can('maintenance.delete'),
            'view_costs' => $can('maintenance.view_costs'),
            'export_pdf' => $can('maintenance.export_pdf'),
            'view_cancellation_details' => Gate::allows('viewAuditLogs')
                && $can('maintenance.view_cancellation_details'),
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
            'cost_date' => ['required', 'date'],
        ]);

        MaintenanceService::addExtraCost($maintenance, $data);

        return back()->with('success', 'Custo avulso lançado com sucesso.');
    }

    public function updateExtraCost(
        Request $request,
        Vehicle $vehicle,
        MaintenanceRecord $maintenance,
        MaintenanceRecordExtraCost $extraCost
    ) {
        if ($redirect = $this->ensureVehicleInActiveContext($vehicle)) {
            return $redirect;
        }

        $this->assertMaintenanceRelation($vehicle, $maintenance);
        abort_unless((int) $extraCost->maintenance_record_id === (int) $maintenance->id, 404);
        $this->authorizeMaintenancePermission('maintenance.edit_extra_costs');
        abort_unless(
            $this->canMaintenance('maintenance.view_costs'),
            403,
            'Você não tem permissão para visualizar ou editar custos.'
        );

        $data = $request->validate([
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0'],
            'cost_date' => ['required', 'date'],
            'change_reason' => ['required', 'string', 'min:10', 'max:2000'],
        ], [
            'change_reason.required' => 'Informe o motivo da alteração.',
            'change_reason.min' => 'O motivo da alteração deve ter pelo menos :min caracteres.',
            'amount.min' => 'O valor do custo avulso não pode ser negativo.',
        ]);

        MaintenanceService::updateExtraCost($maintenance, $extraCost, $data, auth()->user());

        return back()->with('success', 'Custo avulso atualizado com sucesso.');
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
            'materialUsages.stockItem.category',
            'materialUsages.creator',
            'allMaterialUsages.stockItem.category',
            'allMaterialUsages.canceller',
            'allMaterialUsages.replacement.stockItem.category',
            'statusLogs.user',
            'opener',
            'closer',
            'canceller',
            'photos' => fn ($query) => $query->oldest('created_at'),
        ]);

        $pdf = Pdf::loadView('vehicle.pdf.maintenance-order', [
            'vehicle' => $vehicle,
            'maintenance' => $maintenance,
            'canViewCosts' => $this->maintenancePermissions($vehicle)['view_costs'],
            'canViewChanges' => $this->canMaintenance('reports.view_changes'),
            'canViewCancelled' => $this->canMaintenance('reports.view_cancelled'),
            'canViewAuditLogs' => $this->maintenancePermissions($vehicle)['view_cancellation_details'],
        ])->setPaper('a4', 'portrait');

        return $pdf->download(
            'ordem-manutencao-'.$maintenance->id.'.pdf'
        );
    }

}
