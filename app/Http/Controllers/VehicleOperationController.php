<?php

namespace App\Http\Controllers;

use App\Models\Vehicle;
use App\Models\VehicleOperation;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class VehicleOperationController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = auth()->user()->tenant_id ?? null;

        $status = $request->get('status', 'open');

        $operations = VehicleOperation::query()
            ->with(['vehicle', 'driver'])
            ->when($tenantId, function ($query) use ($tenantId) {
                $query->where('tenant_id', $tenantId);
            })
            ->when($status !== 'all', function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $openCount = VehicleOperation::query()
            ->when($tenantId, function ($query) use ($tenantId) {
                $query->where('tenant_id', $tenantId);
            })
            ->where('status', 'open')
            ->count();

        $closedTodayCount = VehicleOperation::query()
            ->when($tenantId, function ($query) use ($tenantId) {
                $query->where('tenant_id', $tenantId);
            })
            ->where('status', 'closed')
            ->whereDate('end_datetime_system', now()->toDateString())
            ->count();

        $driversInOperationCount = VehicleOperation::query()
            ->when($tenantId, function ($query) use ($tenantId) {
                $query->where('tenant_id', $tenantId);
            })
            ->where('status', 'open')
            ->distinct('driver_id')
            ->count('driver_id');

        return view('operations.index', compact(
            'operations',
            'status',
            'openCount',
            'closedTodayCount',
            'driversInOperationCount'
        ));
    }

    public function create(Vehicle $vehicle)
    {
        $openOperation = VehicleOperation::query()
            ->with('driver')
            ->where('vehicle_id', $vehicle->id)
            ->where('status', 'open')
            ->first();
    
        if ($openOperation) {
            return redirect()
                ->route('operations.close', $openOperation->id)
                ->with('info', 'Este veículo já possui uma operação aberta.');
        }
    
        $delayReasons = $this->delayReasons();
    
        $canManageOperationDrivers = $this->canManageOperationDrivers();
    
        $operationDrivers = collect();
    
        if ($canManageOperationDrivers) {
            $activeDivisionId = session('active_division_id');
    
            $vehicleLocationId =
                $vehicle->currentAllocation?->location_id
                ?? $vehicle->location_id
                ?? null;
    
            $driversWithOpenOperations = VehicleOperation::query()
                ->where('status', 'open')
                ->pluck('driver_id')
                ->filter()
                ->unique()
                ->values()
                ->toArray();
    
            $driversQuery = \App\Models\UserDivisionAccess::query()
                ->with('user')
                ->where('division_id', $activeDivisionId)
                ->where('module', 'fleet')
                ->where('profile', 'driver')
                ->where('active', 1);
    
            if ($vehicleLocationId) {
                $driversQuery->where('location_id', $vehicleLocationId);
            }
    
            $operationDrivers = $driversQuery
                ->get()
                ->filter(fn ($access) => $access->user)
                ->map(function ($access) use ($driversWithOpenOperations) {
                    $user = $access->user;
    
                    $user->operation_location_id = $access->location_id;
    
                    $user->has_open_operation =
                        in_array($user->id, $driversWithOpenOperations);
    
                    return $user;
                })
                ->unique('id')
                ->sortBy('name')
                ->values();
        }
    
        return view('operations.start', compact(
            'vehicle',
            'delayReasons',
            'canManageOperationDrivers',
            'operationDrivers'
        ));
    }

    public function store(Request $request, Vehicle $vehicle)
    {
        $reportedStart = Carbon::parse($request->start_datetime_reported);
        $systemNow = now();
        
        if ($reportedStart->greaterThan($systemNow)) {
            return back()
                ->withInput()
                ->withErrors([
                    'start_datetime_reported' => 'A data/hora de início não pode ser futura.',
                ]);
        }
        
        $startDelayMinutes = $reportedStart->diffInMinutes($systemNow, false);
        
        $requiresStartJustification = $startDelayMinutes > 15;

        $rules = [
            'start_vehicle_km' => ['required', 'numeric', 'min:0'],
            'start_vehicle_hours' => ['nullable', 'numeric', 'min:0'],
            'start_datetime_reported' => ['required', 'date'],
            'start_observation' => ['nullable', 'string', 'max:3000'],
        ];

        if ($requiresStartJustification) {
            $rules['start_delay_reason'] = ['required', 'string', 'max:120'];
            $rules['start_delay_justification'] = ['required', 'string', 'max:3000'];
        }

        $validated = $request->validate($rules, [
            'start_delay_reason.required' => 'Informe o motivo do lançamento fora do horário.',
            'start_delay_justification.required' => 'Informe a justificativa do lançamento fora do horário.',
        ]);

        $alreadyOpen = VehicleOperation::query()
            ->where('vehicle_id', $vehicle->id)
            ->where('status', 'open')
            ->exists();

        if ($alreadyOpen) {
            return redirect()
                ->route('vehicles.details', $vehicle->id)
                ->with('error', 'Este veículo já está em operação.');
        }
        
        $canManageOperationDrivers = $this->canManageOperationDrivers();
        
        $cannotStartOperation = $this->cannotStartOperation();
        
        if ($cannotStartOperation) {
            return back()
                ->withInput()
                ->with('error', 'Mecânicos não podem iniciar operações de veículos.');
        }
        
        $driverId = auth()->id();
        
        if ($canManageOperationDrivers) {
            $request->validate([
                'driver_id' => ['required', 'exists:users,id'],
            ], [
                'driver_id.required' => 'Selecione o motorista responsável pela operação.',
            ]);
        
            $driverId = $request->driver_id;
        
            $activeDivisionId = session('active_division_id');
        
            $vehicleLocationId =
                $vehicle->currentAllocation?->location_id
                ?? $vehicle->location_id
                ?? null;
        
            $driverAccessQuery = \App\Models\UserDivisionAccess::query()
                ->where('user_id', $driverId)
                ->where('division_id', $activeDivisionId)
                ->where('module', 'fleet')
                ->where('profile', 'driver')
                ->where('active', 1);
        
            if ($vehicleLocationId) {
                $driverAccessQuery->where('location_id', $vehicleLocationId);
            }
        
            $driverCanOperateThisVehicle = $driverAccessQuery->exists();
        
            if (!$driverCanOperateThisVehicle) {
                return back()
                    ->withInput()
                    ->with('error', 'O motorista selecionado não pertence à mesma divisão/localidade do veículo.');
            }
        }
        
        $driverAlreadyHasOpenOperation = VehicleOperation::query()
            ->where('driver_id', $driverId)
            ->where('status', 'open')
            ->exists();
        
        if ($driverAlreadyHasOpenOperation) {
            return back()
                ->withInput()
                ->with('error', 'Este motorista já possui uma operação aberta. Encerre a operação atual antes de iniciar outra.');
        }

        VehicleOperation::create([
            'tenant_id' => auth()->user()->tenant_id ?? null,
            'vehicle_id' => $vehicle->id,

            'driver_id' => $driverId,
            'status' => 'open',

            'start_vehicle_km' => $validated['start_vehicle_km'],
            'start_vehicle_hours' => $validated['start_vehicle_hours'] ?? null,

            'start_datetime_reported' => $reportedStart,
            'start_datetime_system' => $systemNow,
            'start_clock_difference_minutes' => $startDelayMinutes,
            'start_observation' => $validated['start_observation'] ?? null,
            'start_delay_reason' => $validated['start_delay_reason'] ?? null,
            'start_delay_justification' => $validated['start_delay_justification'] ?? null,

            'created_by' => auth()->id(),
        ]);

        $this->tryUpdateVehicleCounters(
            $vehicle,
            $validated['start_vehicle_km'],
            $validated['start_vehicle_hours'] ?? null
        );

        return redirect()
            ->route('operations.index')
            ->with('success', 'Operação iniciada com sucesso.');
    }

    public function close(VehicleOperation $operation)
    {
        $operation->load(['vehicle', 'driver']);
    
        if ($operation->status !== 'open') {
            return redirect()
                ->route('operations.index')
                ->with('error', 'Esta operação já foi encerrada.');
        }
    
        $canCloseAnyOperation = $this->canManageOperationDrivers();
    
        if (!$canCloseAnyOperation && (int) $operation->driver_id !== (int) auth()->id()) {
            return redirect()
                ->route('operations.index')
                ->with('error', 'Você só pode encerrar operações iniciadas em seu nome.');
        }
    
        $delayReasons = $this->delayReasons();
    
        return view('operations.close', compact(
            'operation',
            'delayReasons'
        ));
    }

    public function finish(Request $request, VehicleOperation $operation)
    {
        if ($operation->status !== 'open') {
            return redirect()
                ->route('operations.index')
                ->with('error', 'Esta operação já foi encerrada.');
        }
    
        $canCloseAnyOperation = $this->canManageOperationDrivers();
    
        if (!$canCloseAnyOperation && (int) $operation->driver_id !== (int) auth()->id()) {
            return redirect()
                ->route('operations.index')
                ->with('error', 'Você só pode encerrar operações iniciadas em seu nome.');
        }
    
        $reportedEnd = Carbon::parse($request->end_datetime_reported);
        $systemNow = now();
    
        if ($reportedEnd->greaterThan($systemNow)) {
            return back()
                ->withInput()
                ->withErrors([
                    'end_datetime_reported' => 'A data/hora final não pode ser futura.',
                ]);
        }
    
        $endDelayMinutes = $reportedEnd->diffInMinutes($systemNow, false);
    
        $rules = [
            'end_vehicle_km' => ['required', 'numeric', 'min:' . (float) $operation->start_vehicle_km],
            'end_vehicle_hours' => ['nullable', 'numeric'],
            'end_datetime_reported' => ['required', 'date', 'before_or_equal:now'],
            'end_observation' => ['nullable', 'string', 'max:3000'],
        ];
    
        if ($operation->start_vehicle_hours !== null) {
            $rules['end_vehicle_hours'][] = 'min:' . (float) $operation->start_vehicle_hours;
        }
    
        $validated = $request->validate($rules, [
            'end_vehicle_km.min' => 'O KM final não pode ser menor que o KM inicial.',
            'end_vehicle_hours.min' => 'O horímetro final não pode ser menor que o horímetro inicial.',
        ]);
    
        DB::transaction(function () use ($operation, $validated, $reportedEnd, $systemNow, $endDelayMinutes) {
            $operation->update([
                'status' => 'closed',
    
                'end_vehicle_km' => $validated['end_vehicle_km'],
                'end_vehicle_hours' => $validated['end_vehicle_hours'] ?? null,
    
                'end_datetime_reported' => $reportedEnd,
                'end_datetime_system' => $systemNow,
                'end_clock_difference_minutes' => $endDelayMinutes,
    
                'end_observation' => $validated['end_observation'] ?? null,
                'end_delay_reason' => null,
                'end_delay_justification' => null,
    
                'closed_by' => auth()->id(),
            ]);
    
            $this->tryUpdateVehicleCounters(
                $operation->vehicle,
                $validated['end_vehicle_km'],
                $validated['end_vehicle_hours'] ?? null
            );
        });
    
        return redirect()
            ->route('operations.index')
            ->with('success', 'Operação encerrada com sucesso.');
    }

    private function delayReasons(): array
    {
        return [
            'system_unavailable' => 'Sistema indisponível no momento',
            'supervisor_authorized' => 'Autorizado pelo supervisor',
            'no_internet' => 'Sem conexão de internet no local',
            'emergency_service' => 'Atendimento emergencial iniciado antes do registro',
            'operational_adjustment' => 'Ajuste operacional posterior',
            'shift_change' => 'Troca de turno ou repasse operacional',
            'other' => 'Outro motivo',
        ];
    }

    private function tryUpdateVehicleCounters(Vehicle $vehicle, $km, $hours = null): void
    {
        $updates = [];

        if (Schema::hasColumn('vehicles', 'current_km')) {
            $updates['current_km'] = $km;
        } elseif (Schema::hasColumn('vehicles', 'odometer')) {
            $updates['odometer'] = $km;
        } elseif (Schema::hasColumn('vehicles', 'mileage')) {
            $updates['mileage'] = $km;
        }

        if ($hours !== null) {
            if (Schema::hasColumn('vehicles', 'current_hours')) {
                $updates['current_hours'] = $hours;
            } elseif (Schema::hasColumn('vehicles', 'hourmeter')) {
                $updates['hourmeter'] = $hours;
            } elseif (Schema::hasColumn('vehicles', 'engine_hours')) {
                $updates['engine_hours'] = $hours;
            }
        }

        if (!empty($updates)) {
            $vehicle->update($updates);
        }
    }

    private function currentDivisionAccess()
    {
        $activeDivisionId = session('active_division_id');
    
        if (!$activeDivisionId) {
            return null;
        }
    
        return \App\Models\UserDivisionAccess::query()
            ->where('user_id', auth()->id())
            ->where('division_id', $activeDivisionId)
            ->where('module', 'fleet')
            ->where('active', 1)
            ->first();
    }
    
    private function currentDivisionRole(): string
    {
        $access = $this->currentDivisionAccess();
    
        return strtolower(
            $access->profile
            ?? auth()->user()->profile
            ?? auth()->user()->role
            ?? auth()->user()->type
            ?? ''
        );
    }
    
    private function canManageOperationDrivers(): bool
    {
        $role = $this->currentDivisionRole();
    
        return in_array($role, [
            'admin',
            'manager',
            'supervisor',
        ]) || (auth()->user()->level ?? 0) >= 50;
    }
    
    private function cannotStartOperation(): bool
    {
        $role = $this->currentDivisionRole();
    
        return in_array($role, [
            'mechanic',
        ]);
    }
}