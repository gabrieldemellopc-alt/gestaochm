<?php

namespace App\Http\Controllers;
use App\Models\Procedure;
use App\Models\MaintenanceRecord;
use App\Models\UserDivisionAccess;
use App\Models\Vehicle;
use App\Services\ActiveContextService;
use Illuminate\Http\Request;
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

        if (! $this->userCanCancelMaintenance($vehicle)) {
            abort(403);
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
        if ((int) $maintenance->vehicle_id !== (int) $vehicle->id) {
            abort(404);
        }
    
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

    private function userCanCancelMaintenance(Vehicle $vehicle): bool
    {
        return UserDivisionAccess::query()
            ->where('tenant_id', auth()->user()->tenant_id)
            ->where('user_id', auth()->id())
            ->where('division_id', $vehicle->division_id)
            ->where('module', 'fleet')
            ->whereIn('profile', ['supervisor', 'manager', 'admin'])
            ->where('active', true)
            ->where(function ($query) use ($vehicle) {
                $query
                    ->where('location_id', $vehicle->location_id)
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
        ])->setPaper('a4', 'portrait');
    
        return $pdf->download(
            'ordem-manutencao-'.$maintenance->id.'.pdf'
        );
    }

}

