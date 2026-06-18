<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Vehicle;
use App\Models\Procedure;
use App\Models\Location;
use App\Models\Division;
use App\Models\VehicleUpdateLog;
use Illuminate\Validation\Rule;
use App\Services\PreventiveService;
use App\Models\StockItem;
use App\Services\ActiveContextService;

class VehicleController extends Controller
{
    public function index()
    {
        $activeDivisionId = session('active_division_id');
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
    
        $vehicles = Vehicle::with([
    
                'maintenances.procedure',
                'procedures',
                'division',
                'location'
    
            ])
            ->where('tenant_id', auth()->user()->tenant_id)
            ->where('division_id', $activeDivisionId)
            ->where('location_id', $activeLocation->id)
            /*
    
                $query->where(
                    'division_id',
                    $activeDivisionId
                );
    
            })
            */
            ->latest()
            ->get();
    
        foreach ($vehicles as $vehicle) {
    
            $vehicle->alerts =
                PreventiveService::getVehicleAlerts($vehicle);
    
            $vehicle->main_alert =
                collect($vehicle->alerts)
                ->sortByDesc(function ($alert) {
    
                    return match ($alert['status']) {
    
                        'danger' => 3,
    
                        'warning' => 2,
    
                        default => 1,
                    };
                })
                ->first();
    
            $vehicle->last_maintenance =
                $vehicle->maintenances
                ->whereNull('cancelled_at')
                ->sortByDesc('performed_at')
                ->first();
        }
    
        return view(
    
            'vehicle.index',
    
            compact('vehicles')
    
        );
    }

    public function create()
    {
        $activeLocation = app(ActiveContextService::class)
            ->activeLocation(auth()->user());

        if (! $activeLocation) {
            return redirect()
                ->route('portal')
                ->with('warning', 'Selecione uma unidade para continuar.');
        }

        $procedures =
            Procedure::where('tenant_id', auth()->user()->tenant_id)
            ->where('location_id', $activeLocation->id)
            ->orderBy('name')
            ->get();

        $divisions = Division::orderBy('name')->get();
        
        $locations = Location::orderBy('name')->get();
        return view(

            'vehicle.create',

            compact('procedures',
                'divisions',
                'locations')

        );
    }

    public function store(Request $request)
    {
        $activeLocation = app(ActiveContextService::class)
            ->activeLocation(auth()->user());

        if (! $activeLocation) {
            return redirect()
                ->route('portal')
                ->with('warning', 'Selecione uma unidade para continuar.');
        }

        /*
        |--------------------------------------------------------------------------
        | NORMALIZAÇÃO DA PLACA
        |--------------------------------------------------------------------------
        */
    
        $rawPlate = strtoupper(
            preg_replace(
                '/[^A-Z0-9]/',
                '',
                $request->plate
            )
        );
    
        if (strlen($rawPlate) >= 4) {
    
            $request->merge([
    
                'plate' =>
                    substr($rawPlate, 0, 3)
                    . '-'
                    . substr($rawPlate, 3, 4),
    
            ]);
    
        } else {
    
            $request->merge([
    
                'plate' =>
                    strtoupper(
                        trim(
                            $request->plate
                        )
                    ),
    
            ]);
    
        }
    
        /*
        |--------------------------------------------------------------------------
        | VALIDAÇÃO
        |--------------------------------------------------------------------------
        */
    
        $validated = $request->validate([
    
            'division_id' => [
                'required',
                'exists:divisions,id',
            ],
    
            'location_id' => [
                'required',
                'exists:locations,id',
                Rule::in([$activeLocation->id]),
            ],
    
            'type' => [
                'required',
                'string',
            ],
            'tire_layout' => [
                'nullable',
                'string',
                'in:car_4_single,truck_6_mixed,truck_8_mixed,truck_10_mixed,truck_12_mixed',
            ],
    
            'name' => [
                'required',
                'string',
                'max:255',
            ],
    
            'plate' => [
                'required',
                'regex:/^[A-Z]{3}-[A-Z0-9]{4}$/',
                Rule::unique('vehicles', 'plate'),
            ],
    
            'brand' => [
                'nullable',
                'string',
                'max:255',
            ],
    
            'model' => [
                'nullable',
                'string',
                'max:255',
            ],
    
            'year' => [
                'nullable',
                'integer',
            ],
    
            'current_km' => [
                'nullable',
                'numeric',
                'min:0',
            ],
    
            'current_hours' => [
                'nullable',
                'numeric',
                'min:0',
            ],
    
            'operational_status' => [
                'nullable',
                'in:operational,maintenance',
            ],
    
            'status' => [
                'nullable',
                'in:active,inactive',
            ],
    
            'operation_started_at' => [
                'nullable',
                'date',
            ],
    
            'notes' => [
                'nullable',
                'string',
            ],
    
            'procedures' => [
                'nullable',
                'array',
            ],
    
            'procedures.*' => [
                Rule::exists('procedures', 'id')
                    ->where(fn ($query) => $query
                        ->where('tenant_id', auth()->user()->tenant_id)
                        ->where('location_id', $activeLocation->id)),
            ],
    
        ], [
    
            'division_id.required' =>
                'Informe a divisão do veículo.',
    
            'location_id.required' =>
                'Informe a localidade do veículo.',
    
            'name.required' =>
                'Informe o nome do veículo.',
    
            'plate.required' =>
                'Informe a placa do veículo.',
    
            'plate.regex' =>
                'A placa deve estar no formato ABC-1D23.',
    
            'plate.unique' =>
                'Já existe outro veículo cadastrado com esta placa.',
    
            'current_km.numeric' =>
                'O hodômetro deve ser um número válido.',
    
            'current_km.min' =>
                'O hodômetro não pode ser negativo.',
    
            'current_hours.numeric' =>
                'O horímetro deve ser um número válido.',
    
            'current_hours.min' =>
                'O horímetro não pode ser negativo.',
    
            'year.integer' =>
                'O ano deve ser um número válido.',
    
        ]);
    
        /*
        |--------------------------------------------------------------------------
        | CRIAÇÃO DO VEÍCULO
        |--------------------------------------------------------------------------
        */
    
        $vehicle = Vehicle::create([
    
            'tenant_id' =>
                auth()->user()->tenant_id,
    
            'division_id' =>
                $validated['division_id'],
    
            'location_id' =>
                $validated['location_id'],
    
            'type' =>
                $validated['type'],
    
            'name' =>
                $validated['name'],
    
            'plate' =>
                $validated['plate'],
    
            'brand' =>
                $validated['brand'] ?? null,
    
            'model' =>
                $validated['model'] ?? null,
    
            'year' =>
                $validated['year'] ?? null,
            'tire_layout' =>
                $request->tire_layout ?? 'truck_6_mixed',
            'current_km' =>
                $validated['current_km'] ?? 0,
    
            'current_hours' =>
                $validated['current_hours'] ?? 0,
    
            'last_km_update_at' =>
                now(),
    
            'last_hours_update_at' =>
                now(),
    
            'operational_status' =>
                $validated['operational_status'] ?? 'operational',
    
            'status' =>
                $validated['status'] ?? 'active',
    
            'operation_started_at' =>
                $validated['operation_started_at'] ?? null,
    
            'notes' =>
                $validated['notes'] ?? null,
    
        ]);
    
        /*
        |--------------------------------------------------------------------------
        | LOG INICIAL KM
        |--------------------------------------------------------------------------
        */
    
        VehicleUpdateLog::create([
    
            'vehicle_id' =>
                $vehicle->id,
    
            'user_id' =>
                auth()->id(),
    
            'division_id' =>
                $vehicle->division_id,
    
            'location_id' =>
                $vehicle->location_id,
    
            'type' =>
                'km',
    
            'old_value' =>
                null,
    
            'new_value' =>
                $vehicle->current_km,
    
            'observation' =>
                'Registro inicial do veículo',
    
        ]);
    
        /*
        |--------------------------------------------------------------------------
        | LOG INICIAL HORAS
        |--------------------------------------------------------------------------
        */
    
        VehicleUpdateLog::create([
    
            'vehicle_id' =>
                $vehicle->id,
    
            'user_id' =>
                auth()->id(),
    
            'division_id' =>
                $vehicle->division_id,
    
            'location_id' =>
                $vehicle->location_id,
    
            'type' =>
                'hours',
    
            'old_value' =>
                null,
    
            'new_value' =>
                $vehicle->current_hours,
    
            'observation' =>
                'Registro inicial do veículo',
    
        ]);
    
        /*
        |--------------------------------------------------------------------------
        | PROCEDIMENTOS
        |--------------------------------------------------------------------------
        */
    
        $vehicle->procedures()->sync(
    
            $this->procedureIdsInContext(
                $validated['procedures'] ?? [],
                (int) $vehicle->tenant_id,
                (int) $vehicle->location_id
            )
    
        );
    
        return redirect()
    
            ->route('vehicles.index')
    
            ->with(
    
                'success',
    
                'Veículo cadastrado com sucesso.'
    
            );
    }

    public function edit(Vehicle $vehicle)
    {
        if ($redirect = $this->ensureVehicleInActiveContext($vehicle)) {
            return $redirect;
        }

        $procedures =
            Procedure::where('tenant_id', $vehicle->tenant_id)
            ->where('location_id', $vehicle->location_id)
            ->orderBy('name')
            ->get();

        $vehicle->load([
            'procedures' => function ($query) use ($vehicle) {
                $query
                    ->where('tenant_id', $vehicle->tenant_id)
                    ->where('location_id', $vehicle->location_id);
            },
        ]);
        $divisions = Division::orderBy('name')->get();
        
        $locations = Location::orderBy('name')->get();
        return view(

            'vehicle.edit',

            compact(
                'vehicle',
                'procedures',
                'divisions',
                'locations'
            )

        );
    }

    public function update(
        Request $request,
        Vehicle $vehicle
    ) {
        if ($redirect = $this->ensureVehicleInActiveContext($vehicle)) {
            return $redirect;
        }
    
        /*
        |--------------------------------------------------------------------------
        | NORMALIZAÇÃO DA PLACA
        |--------------------------------------------------------------------------
        */
    
        $rawPlate = strtoupper(
            preg_replace(
                '/[^A-Z0-9]/',
                '',
                $request->plate
            )
        );
    
        if (strlen($rawPlate) >= 4) {
    
            $request->merge([
    
                'plate' =>
                    substr($rawPlate, 0, 3)
                    . '-'
                    . substr($rawPlate, 3, 4),
    
            ]);
    
        } else {
    
            $request->merge([
    
                'plate' =>
                    strtoupper(
                        trim(
                            $request->plate
                        )
                    ),
    
            ]);
    
        }
    
        /*
        |--------------------------------------------------------------------------
        | VALIDAÇÃO
        |--------------------------------------------------------------------------
        */
    
        $validated = $request->validate([
    
            'name' => [
                'required',
                'string',
                'max:255',
            ],
    
            'plate' => [
                'required',
                'regex:/^[A-Z]{3}-[A-Z0-9]{4}$/',
                Rule::unique('vehicles', 'plate')
                    ->ignore($vehicle->id),
            ],
    
            'brand' => [
                'nullable',
                'string',
                'max:255',
            ],
    
            'model' => [
                'nullable',
                'string',
                'max:255',
            ],
    
            'year' => [
                'nullable',
                'integer',
            ],
    
            'current_km' => [
                'nullable',
                'numeric',
                'min:' . ($vehicle->current_km ?? 0),
            ],
    
            'current_hours' => [
                'nullable',
                'numeric',
                'min:' . ($vehicle->current_hours ?? 0),
            ],
    
            'status' => [
                'required',
                'in:active,inactive',
            ],
    
            'operational_status' => [
                'required',
                'in:operational,maintenance',
            ],
    
            'operation_started_at' => [
                'nullable',
                'date',
            ],
    
            'notes' => [
                'nullable',
                'string',
            ],
    
            'type' => [
                'required',
                'string',
            ],
    
            'division_id' => [
                'required',
                'exists:divisions,id',
            ],
            'tire_layout' => [
                'nullable',
                'string',
                'in:car_4_single,truck_6_mixed,truck_8_mixed,truck_10_mixed,truck_12_mixed',
            ],
            'location_id' => [
                'required',
                'exists:locations,id',
                Rule::in([$vehicle->location_id]),
            ],
    
            'procedures' => [
                'nullable',
                'array',
            ],
    
            'procedures.*' => [
                Rule::exists('procedures', 'id')
                    ->where(fn ($query) => $query
                        ->where('tenant_id', $vehicle->tenant_id)
                        ->where('location_id', $vehicle->location_id)),
            ],
    
        ], [
    
            'name.required' =>
                'Informe o nome do veículo.',
    
            'plate.required' =>
                'Informe a placa do veículo.',
    
            'plate.regex' =>
                'A placa deve estar no formato ABC-1D23.',
    
            'plate.unique' =>
                'Já existe outro veículo cadastrado com esta placa.',
    
            'year.integer' =>
                'O ano deve ser um número válido.',
    
            'current_km.numeric' =>
                'O hodômetro deve ser um número válido.',
    
            'current_km.min' =>
                'O hodômetro não pode ser menor que o valor atual.',
    
            'current_hours.numeric' =>
                'O horímetro deve ser um número válido.',
    
            'current_hours.min' =>
                'O horímetro não pode ser menor que o valor atual.',
    
            'status.required' =>
                'Informe a situação do veículo.',
    
            'operational_status.required' =>
                'Informe o status operacional do veículo.',
    
            'division_id.required' =>
                'Informe a divisão do veículo.',
    
            'location_id.required' =>
                'Informe a localidade do veículo.',
    
        ]);
    
        /*
        |--------------------------------------------------------------------------
        | SNAPSHOT ANTIGO
        |--------------------------------------------------------------------------
        */
    
        if ($redirect = $this->ensureVehicleInActiveContext($vehicle)) {
            return $redirect;
        }

        $oldData = $vehicle->replicate();
    
        /*
        |--------------------------------------------------------------------------
        | UPDATE
        |--------------------------------------------------------------------------
        */
    
        $vehicle->update([
    
            'name' =>
                $validated['name'],
    
            'plate' =>
                $validated['plate'],
    
            'brand' =>
                $validated['brand'] ?? null,
    
            'model' =>
                $validated['model'] ?? null,
    
            'year' =>
                $validated['year'] ?? null,
    
            'current_km' =>
                $validated['current_km'] ?? 0,
    
            'current_hours' =>
                $validated['current_hours'] ?? 0,
    
            'status' =>
                $validated['status'],
    
            'operational_status' =>
                $validated['operational_status'],
    
            'operation_started_at' =>
                $validated['operation_started_at'] ?? null,
    
            'notes' =>
                $validated['notes'] ?? null,
    
            'type' =>
                $validated['type'],
            'tire_layout' =>
                $request->tire_layout ?? 'truck_6_mixed',
            'division_id' =>
                $validated['division_id'],
    
            'location_id' =>
                $validated['location_id'],
    
        ]);
    
        /*
        |--------------------------------------------------------------------------
        | LOGS DE ALTERAÇÃO
        |--------------------------------------------------------------------------
        */
    
        $fieldsToTrack = [
    
            'current_km' => 'km',
    
            'current_hours' => 'hours',
    
            'status' => 'status',
    
            'operational_status' =>
                'operational_status',
    
            'division_id' => 'division',
    
            'location_id' => 'location',
    
            'type' => 'type',
    
        ];
    
        foreach ($fieldsToTrack as $field => $type) {
    
            if (
                $oldData->$field
                != $vehicle->$field
            ) {
    
                VehicleUpdateLog::create([
    
                    'vehicle_id' =>
                        $vehicle->id,
    
                    'user_id' =>
                        auth()->id(),
    
                    'division_id' =>
                        $vehicle->division_id,
    
                    'location_id' =>
                        $vehicle->location_id,
    
                    'type' =>
                        $type,
    
                    'old_value' =>
                        $oldData->$field,
    
                    'new_value' =>
                        $vehicle->$field,
    
                ]);
    
            }
    
        }
    
        /*
        |--------------------------------------------------------------------------
        | TIMESTAMPS OPERACIONAIS
        |--------------------------------------------------------------------------
        */
    
        if (
            $oldData->current_km
            != $vehicle->current_km
        ) {
    
            $vehicle->update([
    
                'last_km_update_at' => now()
    
            ]);
    
        }
    
        if (
            $oldData->current_hours
            != $vehicle->current_hours
        ) {
    
            $vehicle->update([
    
                'last_hours_update_at' => now()
    
            ]);
    
        }
    
        /*
        |--------------------------------------------------------------------------
        | PROCEDIMENTOS
        |--------------------------------------------------------------------------
        */
    
        $vehicle->procedures()->sync(
    
            $this->procedureIdsInContext(
                $validated['procedures'] ?? [],
                (int) $vehicle->tenant_id,
                (int) $vehicle->location_id
            )
    
        );
    
        return redirect()
    
            ->route('vehicles.index')
    
            ->with(
    
                'success',
    
                'Veículo atualizado com sucesso.'
    
            );
    }
    
    public function details(Vehicle $vehicle)
    {
        /*
        |--------------------------------------------------------------------------
        | DIVISÃO ATIVA
        |--------------------------------------------------------------------------
        */
    
        if (! session('active_division_id')) {
    
            return redirect()
                ->route('portal')
                ->with(
                    'warning',
                    'Selecione uma divisão para continuar.'
                );
        }
    
        /*
        |--------------------------------------------------------------------------
        | ESCOPO DA DIVISÃO
        |--------------------------------------------------------------------------
        */
    
        if (
            (int) $vehicle->division_id !== (int) session('active_division_id')
        ) {
            abort(403);
        }
    
        /*
        |--------------------------------------------------------------------------
        | CARREGAMENTOS
        |--------------------------------------------------------------------------
        */
    
        if ($redirect = $this->ensureVehicleInActiveContext($vehicle)) {
            return $redirect;
        }

        $vehicle->load([
            'maintenances.procedure',
            'procedures' => function ($query) use ($vehicle) {
                $query
                    ->where('tenant_id', $vehicle->tenant_id)
                    ->where('location_id', $vehicle->location_id);
            },
            'activeMaintenances.procedure',
            'updateLogs.user',
            'currentAllocation.location',
            'currentAllocation.division',
        ]);
    
        /*
        |--------------------------------------------------------------------------
        | ALERTAS / STATUS
        |--------------------------------------------------------------------------
        */
    
        $vehicle->alerts =
            PreventiveService::getVehicleAlerts($vehicle);
    
        $vehicle->main_alert =
            collect($vehicle->alerts)
                ->sortByDesc(function ($alert) {
                    return match ($alert['status']) {
                        'danger' => 3,
                        'warning' => 2,
                        default => 1,
                    };
                })
                ->first();
    
        $vehicle->alert_status =
            PreventiveService::getVehicleStatus($vehicle);
    
        if (! $vehicle->operational_status) {
            $vehicle->operational_status =
                'operational';
        }
    
        /*
        |--------------------------------------------------------------------------
        | APOIO PARA MANUTENÇÃO
        |--------------------------------------------------------------------------
        */
    
        $procedures =
            Procedure::with('fields.stockCategory')
                ->where('tenant_id', $vehicle->tenant_id)
                ->where('location_id', $vehicle->location_id)
                ->get();
    
        $stockItems =
            StockItem::where('active', true)
                ->get();
    
        return view(
            'vehicle.details',
            compact(
                'vehicle',
                'procedures',
                'stockItems'
            )
        );
    }

    public function updateOperationalStatus(
        Request $request,
        Vehicle $vehicle
    ) {
    
        if ($redirect = $this->ensureVehicleInActiveContext($vehicle)) {
            return $redirect;
        }

        $request->validate([
    
            'operational_status' => [
    
                'required',
    
                'in:operational,maintenance'
            ]
    
        ]);
    
        $vehicle->update([
    
            'operational_status' =>
                $request->operational_status
    
        ]);
    
        return response()->json([
    
        'success' => true,
    
        'message' => 'Status operacional atualizado.'
    
    ]);
    }
    
    public function updateKm(
        Request $request,
        Vehicle $vehicle
    ) {
    
        $request->validate([
    
            'km' => [
    
                'required',
                'numeric',
                'min:0'
            ]
    
        ]);
    
        if ($redirect = $this->ensureVehicleInActiveContext($vehicle)) {
            return $redirect;
        }

        $oldKm = $vehicle->current_km;
        if (
            $oldKm !== null
            &&
            (float) $request->km < (float) $oldKm
        ) {
            return response()->json([
                'success' => false,
                'message' => 'O novo hodômetro não pode ser menor que o atual.'
            ], 422);
        }
        $vehicle->update([
    
            'current_km' =>
                $request->km,
    
            'last_km_update_at' =>
                now()
    
        ]);
    
        VehicleUpdateLog::create([
    
            'vehicle_id' => $vehicle->id,
    
            'user_id' => auth()->id(),
    
            'division_id' => $vehicle->division_id,
    
            'location_id' => $vehicle->location_id,
    
            'type' => 'km',
    
            'old_value' => $oldKm,
            'source' => 'dashboard_quick_update',
            
            'observation' => 'Hodômetro atualizado manualmente pelo painel rápido.',
            'new_value' => $request->km,
    
        ]);
    
        return response()->json([
    
        'success' => true,
    
        'message' => 'Hodômetro atualizado.'
    
    ]);
    }
    
    public function updateHours(
        Request $request,
        Vehicle $vehicle
    ) {
    
        $request->validate([
    
            'hours' => [
    
                'required',
                'numeric',
                'min:0'
            ]
    
        ]);
    
        if ($redirect = $this->ensureVehicleInActiveContext($vehicle)) {
            return $redirect;
        }

        $oldHours = $vehicle->current_hours;
        if (
            $oldHours !== null
            &&
            (float) $request->hours < (float) $oldHours
        ) {
            return response()->json([
                'success' => false,
                'message' => 'O novo horímetro não pode ser menor que o atual.'
            ], 422);
        }
        $vehicle->update([
    
            'current_hours' =>
                $request->hours,
    
            'last_hours_update_at' =>
                now()
    
        ]);
    
        VehicleUpdateLog::create([
        
            'vehicle_id' => $vehicle->id,
        
            'user_id' => auth()->id(),
        
            'division_id' => $vehicle->division_id,
        
            'location_id' => $vehicle->location_id,
        
            'type' => 'hours',
        
            'source' => 'dashboard_quick_update',
        
            'old_value' => $oldHours,
        
            'new_value' => $request->hours,
        
            'observation' => 'Horímetro atualizado manualmente pelo painel rápido.',
        
        ]);
    
        return response()->json([
    
        'success' => true,
    
        'message' => 'Horímetro atualizado.'
    
    ]);
    }
    
    public function history(Vehicle $vehicle)
    {
        $vehicle->load([
    
            'updateLogs.user',
    
            'maintenances.procedure'
        ]);
        if ($redirect = $this->ensureVehicleInActiveContext($vehicle)) {
            return $redirect;
        }

        $locationLogs =
            $vehicle->updateLogs
                ->where('type', 'location')
                ->sortBy('created_at')
                ->map(function ($log) {
        
                    $log->old_location_name =
                        \App\Models\Location::find($log->old_value)?->name;
        
                    $log->new_location_name =
                        \App\Models\Location::find($log->new_value)?->name;
        
                    return $log;
                });
        return view(
    
            'vehicle.history',
    
            compact('vehicle','locationLogs')
        );
    }

    public function quickUpdate()
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

        $vehicles = Vehicle::query()
            ->where(
                'division_id',
                session('active_division_id')
            )
            ->where('tenant_id', auth()->user()->tenant_id)
            ->where('location_id', $activeLocation->id)
            ->orderBy('plate')
            ->get();
    
        foreach ($vehicles as $vehicle) {
    
            $vehicle->alerts =
                PreventiveService::getVehicleAlerts($vehicle);
    
            $vehicle->main_alert =
                collect($vehicle->alerts)
                    ->sortByDesc(function ($alert) {
                        return match ($alert['status']) {
                            'danger' => 3,
                            'warning' => 2,
                            default => 1,
                        };
                    })
                    ->first();
    
            $vehicle->alert_status =
                PreventiveService::getVehicleStatus($vehicle);
    
            $vehicle->operational_update_alerts =
                collect($vehicle->alerts)
                    ->filter(function ($alert) {
                        return
                            isset($alert['procedure'])
                            &&
                            $alert['procedure'] === 'Atualização operacional';
                    })
                    ->values();
        }
    
        return view(
            'vehicle.quick-update',
            compact('vehicles')
        );
    }
    
    public function quickUpdateStore(Request $request)
    {
        $activeDivisionId = session('active_division_id');
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

        $data = $request->validate([
            'vehicles' => ['required', 'array'],
    
            'vehicles.*.id' => [
                'required',
                'exists:vehicles,id',
            ],
    
            'vehicles.*.current_km' => [
                'nullable',
                'numeric',
                'min:0',
            ],
    
            'vehicles.*.current_hours' => [
                'nullable',
                'numeric',
                'min:0',
            ],
        ]);
    
        $updated = 0;
    
        foreach ($data['vehicles'] as $vehicleData) {
    
            $vehicle = Vehicle::query()
                ->where('tenant_id', auth()->user()->tenant_id)
                ->where('division_id', $activeDivisionId)
                ->where('location_id', $activeLocation->id)
                ->find($vehicleData['id']);
    
            if (!$vehicle) {
                continue;
            }
    
            $updateData = [];
    
            $logs = [];
    
            /*
            |--------------------------------------------------------------------------
            | KM
            |--------------------------------------------------------------------------
            */
            if (
                array_key_exists('current_km', $vehicleData)
                &&
                $vehicleData['current_km'] !== null
                &&
                $vehicleData['current_km'] !== ''
            ) {
                $oldKmRaw = $vehicle->current_km;
                $oldKm = (float) $vehicle->current_km;
                $newKm = (float) $vehicleData['current_km'];
    
                if ($newKm != $oldKm) {
    
                    if (
                        $vehicle->current_km === null
                        ||
                        $newKm >= $oldKm
                    ) {
                        $updateData['current_km'] =
                            $newKm;
    
                        $updateData['last_km_update_at'] =
                            now();
    
                        $logs[] = [
                            'type' => 'km',
                            'old_value' => $oldKmRaw,
                            'new_value' => $newKm,
                        ];
                    }
                }
            }
    
            /*
            |--------------------------------------------------------------------------
            | HORÍMETRO
            |--------------------------------------------------------------------------
            */
            if (
                array_key_exists('current_hours', $vehicleData)
                &&
                $vehicleData['current_hours'] !== null
                &&
                $vehicleData['current_hours'] !== ''
            ) {
                $oldHoursRaw = $vehicle->current_hours;
                $oldHours = (float) $vehicle->current_hours;
                $newHours = (float) $vehicleData['current_hours'];
    
                if ($newHours != $oldHours) {
    
                    if (
                        $vehicle->current_hours === null
                        ||
                        $newHours >= $oldHours
                    ) {
                        $updateData['current_hours'] =
                            $newHours;
    
                        $updateData['last_hours_update_at'] =
                            now();
    
                        $logs[] = [
                            'type' => 'hours',
                            'old_value' => $oldHoursRaw,
                            'new_value' => $newHours,
                        ];
                    }
                }
            }
    
            /*
            |--------------------------------------------------------------------------
            | SALVA VEÍCULO + LOGS
            |--------------------------------------------------------------------------
            */
            if (!empty($updateData)) {
    
                $vehicle->update($updateData);
    
                foreach ($logs as $log) {
    
                    VehicleUpdateLog::create([
                        'vehicle_id' => $vehicle->id,
    
                        'user_id' => auth()->id(),
    
                        'division_id' => $vehicle->division_id,
    
                        'location_id' => $vehicle->location_id,
    
                        'type' => $log['type'],
    
                        'old_value' => $log['old_value'],
    
                        'new_value' => $log['new_value'],
                    ]);
                }
    
                $updated++;
            }
        }
    
        return redirect()
            ->route('vehicle.quick-update')
            ->with(
                'success',
                "{$updated} veículo(s) atualizado(s) com sucesso."
            );
    }


    private function procedureIdsInContext(array $procedureIds, int $tenantId, int $locationId): array
    {
        $procedureIds = collect($procedureIds)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $validIds = Procedure::query()
            ->where('tenant_id', $tenantId)
            ->where('location_id', $locationId)
            ->whereIn('id', $procedureIds)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();

        if ($validIds->count() !== $procedureIds->count()) {
            abort(403);
        }

        return $validIds->all();
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

public function maintenanceCreate(
    Request $request,
    Vehicle $vehicle
) {
    if ($redirect = $this->ensureVehicleInActiveContext($vehicle)) {
        return $redirect;
    }

    $data = $request->validate([

        'procedure_id' => [
            'required',
            Rule::exists('procedures', 'id')
                ->where(fn ($query) => $query
                    ->where('tenant_id', $vehicle->tenant_id)
                    ->where('location_id', $vehicle->location_id)),
        ],

        'execution_type' => [
            'required',
            'in:internal,external',
        ],

    ], [

        'procedure_id.required' =>
            'Selecione um procedimento.',

        'execution_type.required' =>
            'Selecione o tipo de execução.',

    ]);

    $vehicle->load([
        'division',
        'location',
    ]);

    $procedure =
        Procedure::with([
            'fields.stockCategory.items' => function ($query) use ($vehicle) {
                $query
                    ->where('tenant_id', $vehicle->tenant_id)
                    ->where('location_id', $vehicle->location_id)
                    ->where('active', true);
            },
        ])
        ->where('tenant_id', $vehicle->tenant_id)
        ->where('location_id', $vehicle->location_id)
        ->findOrFail(
            $data['procedure_id']
        );

    if (
        $data['execution_type'] === 'internal'
        &&
        !$procedure->can_be_internal
    ) {
        return redirect()
            ->route('vehicles.index')
            ->withErrors([
                'execution_type' =>
                    'Este procedimento não permite execução em oficina interna.',
            ]);
    }

    return view(
        'vehicle.maintenance-create',
        [
            'vehicle' =>
                $vehicle,

            'procedure' =>
                $procedure,

            'executionType' =>
                $data['execution_type'],
        ]
    );
}
 
 public function maintenanceIndex(Vehicle $vehicle)
{
    if ($redirect = $this->ensureVehicleInActiveContext($vehicle)) {
        return $redirect;
    }

    $vehicle->load([
        'division',
        'location',
        'maintenances.procedure',
    ]);

    $vehicle->load([
        'division',
        'location',
        'maintenances.procedure',
        'procedures' => function ($query) use ($vehicle) {
            $query
                ->where('tenant_id', $vehicle->tenant_id)
                ->where('location_id', $vehicle->location_id);
        },
        'procedures.fields.stockCategory.items' => function ($query) use ($vehicle) {
            $query
                ->where('tenant_id', $vehicle->tenant_id)
                ->where('location_id', $vehicle->location_id)
                ->where('active', true);
        },
    ]);
    
    $procedures =
        $vehicle
            ->procedures
            ->sortBy('name')
            ->values();

    /*
    |--------------------------------------------------------------------------
    | ATALHOS DE ALERTA
    |--------------------------------------------------------------------------
    | Aqui montamos cards rápidos para procedimentos que exigem atenção.
    | A lógica abaixo é simples e pode ser refinada depois com base nas suas regras.
    */

    $alertProcedures =
        $procedures
            ->map(function ($procedure) use ($vehicle) {

                $lastMaintenance =
                    $vehicle
                        ->maintenances
                        ->whereNull('cancelled_at')
                        ->where('procedure_id', $procedure->id)
                        ->sortByDesc('performed_at')
                        ->first();

                $status = 'ok';
                $reason = null;

                if ($procedure->validity_km && $procedure->interval_km > 0) {

                    $baseKm =
                        $lastMaintenance->performed_km ?? 0;

                    $kmSince =
                        ($vehicle->current_km ?? 0) - $baseKm;

                    if ($kmSince >= $procedure->interval_km) {
                        $status = 'danger';
                        $reason = 'Manutenção vencida por KM';
                    } elseif ($kmSince >= ($procedure->interval_km * 0.8)) {
                        $status = 'warning';
                        $reason = 'Manutenção próxima por KM';
                    }
                }

                if ($procedure->validity_hours && $procedure->interval_hours > 0) {

                    $baseHours =
                        $lastMaintenance->performed_hours ?? 0;

                    $hoursSince =
                        ($vehicle->current_hours ?? 0) - $baseHours;

                    if ($hoursSince >= $procedure->interval_hours) {
                        $status = 'danger';
                        $reason = 'Manutenção vencida por horímetro';
                    } elseif ($hoursSince >= ($procedure->interval_hours * 0.8)) {
                        $status = $status === 'danger' ? 'danger' : 'warning';
                        $reason = $reason ?? 'Manutenção próxima por horímetro';
                    }
                }

                if ($procedure->validity_period && $procedure->interval_days > 0) {

                    if ($lastMaintenance && $lastMaintenance->performed_at) {

                        $daysSince =
                            $lastMaintenance->performed_at->diffInDays(now());

                        if ($daysSince >= $procedure->interval_days) {
                            $status = 'danger';
                            $reason = 'Manutenção vencida por período';
                        } elseif ($daysSince >= ($procedure->interval_days * 0.8)) {
                            $status = $status === 'danger' ? 'danger' : 'warning';
                            $reason = $reason ?? 'Manutenção próxima por período';
                        }

                    } else {
                        $status = 'warning';
                        $reason = 'Procedimento ainda não lançado';
                    }
                }

                $procedure->alert_status = $status;
                $procedure->alert_reason = $reason;

                return $procedure;

            })
            ->filter(function ($procedure) {
                return in_array($procedure->alert_status, [
                    'warning',
                    'danger',
                ]);
            })
            ->values();

    return view(
        'vehicle.maintenance-index',
        compact(
            'vehicle',
            'procedures',
            'alertProcedures'
        )
    );
}
    
}
