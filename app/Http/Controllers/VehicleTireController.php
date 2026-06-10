<?php

namespace App\Http\Controllers;

use App\Models\Tire;
use App\Models\TireInstallation;
use App\Models\VehicleUpdateLog;
use App\Models\Vehicle;
use App\Models\Division;
use App\Models\VehicleTirePosition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\TireMeasurement;
use App\Services\ActiveContextService;

class VehicleTireController extends Controller
{
    public function index(Vehicle $vehicle)
    {
        if ($redirect = $this->ensureVehicleInActiveContext($vehicle)) {
            return $redirect;
        }

        $authUser = auth()->user();

        if (
            $authUser->id !== 1
            &&
            (int) $vehicle->tenant_id !== (int) $authUser->tenant_id
        ) {
            abort(403);
        }

        $vehicle->load([
            'tirePositions',
            'activeTireInstallations.tire' =>
                fn ($query) => $query
                    ->withCurrentTreadContext()
                    ->withCount('retreads'),
        ]);

        /*
        |--------------------------------------------------------------------------
        | CRIA POSIÇÕES PADRÃO SE O VEÍCULO AINDA NÃO TIVER
        |--------------------------------------------------------------------------
        */

        if (
            $vehicle->tirePositions()->count() === 0
        ) {
            $this->createDefaultPositions($vehicle);

            $vehicle->load([
                'tirePositions',
                'activeTireInstallations.tire' =>
                    fn ($query) => $query
                        ->withCurrentTreadContext()
                        ->withCount('retreads'),
            ]);
        }

        $positions =
            $vehicle
                ->tirePositions()
                ->where('active', true)
                ->orderBy('sort_order')
                ->get()
                ->map(function ($position) use ($vehicle) {

                    $installation =
                        $vehicle
                            ->activeTireInstallations
                            ->firstWhere(
                                'position_code',
                                $position->code
                            );

                    $latestMeasurement =
                        $installation
                            ? TireMeasurement::where('tenant_id', $vehicle->tenant_id)
                                ->where('tire_id', $installation->tire_id)
                                ->where('vehicle_id', $vehicle->id)
                                ->where('position_code', $position->code)
                                ->latest('measured_at')
                                ->latest('id')
                                ->first()
                            : null;

                    return [
                        'id' =>
                            $position->id,

                        'code' =>
                            $position->code,

                        'label' =>
                            $position->label,

                        'installation' =>
                            $installation,

                        'tire' =>
                            $installation?->tire,
                        'wear_percent' =>
                            $this->treadWearPercent(
                                $installation?->tire
                            ),
                        'latest_measurement' =>
                            $latestMeasurement,

                        'status' =>
                            $this->positionStatus($installation, $latestMeasurement),
                    ];
                });

        $availableTires =
            Tire::where('tenant_id', $vehicle->tenant_id)
                ->withCurrentTreadContext()
                ->withCount('retreads')
                ->where('location_id', $vehicle->location_id)
                ->where('status', 'available')
                ->orderBy('code')
                ->get()
                ->map(function ($tire) {
                    return [
                        'id' =>
                            $tire->id,
        
                        'code' =>
                            $tire->code,
        
                        'brand' =>
                            $tire->brand,
        
                        'model' =>
                            $tire->model,
        
                        'size' =>
                            $tire->size,
        
                        'initial_tread_depth' =>
                            $tire->initial_tread_depth,
        
                        'label' =>
                            trim(
                                $tire->code
                                . ' · '
                                . ($tire->brand ?? 'Sem marca')
                                . ($tire->model ? ' · ' . $tire->model : '')
                                . ($tire->size ? ' · ' . $tire->size : '')
                            ),
                    ];
                })
                ->values();

        return view(
            'vehicle.tires.index',
            compact(
                'vehicle',
                'positions',
                'availableTires'
            )
        );
    }

    public function storeInstallation(Request $request, Vehicle $vehicle)
    {
        if ($redirect = $this->ensureVehicleInActiveContext($vehicle)) {
            return $redirect;
        }

        $authUser = auth()->user();

        if (
            $authUser->id !== 1
            &&
            (int) $vehicle->tenant_id !== (int) $authUser->tenant_id
        ) {
            abort(403);
        }

        $data =
            $request->validate([
                'position_code' => [
                    'required',
                    'string',
                    'max:20',
                ],

                'tire_id' => [
                    'required',
                    'exists:tires,id',
                ],

                'installed_at' => [
                    'nullable',
                    'date',
                ],

                'installed_km' => [
                    'nullable',
                    'integer',
                    'min:0',
                ],
            ]);

        DB::transaction(function () use ($data, $vehicle, $authUser) {

            $tire = Tire::query()
                ->where('id', $data['tire_id'])
                ->where('tenant_id', $vehicle->tenant_id)
                ->where('location_id', $vehicle->location_id)
                ->where('status', 'available')
                ->withCurrentTreadContext()
                ->lockForUpdate()
                ->first();

            if (! $tire) {
                throw ValidationException::withMessages([
                    'tire_id' => 'O pneu selecionado não está disponível para esta unidade.',
                ]);
            }

            if ($tire->activeInstallation()->exists()) {
                throw ValidationException::withMessages([
                    'tire_id' => 'O pneu selecionado já possui uma instalação ativa.',
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | REMOVE PNEU ATIVO DA POSIÇÃO, SE EXISTIR
            |--------------------------------------------------------------------------
            */

            TireInstallation::where('tenant_id', $vehicle->tenant_id)
                ->where('vehicle_id', $vehicle->id)
                ->where('position_code', $data['position_code'])
                ->where('active', true)
                ->update([
                    'active' =>
                        false,

                    'removed_at' =>
                        now()->toDateString(),

                    'removed_km' =>
                        $vehicle->current_km,

                    'removal_reason' =>
                        'Substituição de pneu',
                ]);

            /*
            |--------------------------------------------------------------------------
            | SE ESSE PNEU ESTIVER INSTALADO EM OUTRO LUGAR, REMOVE DE LÁ
            |--------------------------------------------------------------------------
            */

            TireInstallation::where('tenant_id', $vehicle->tenant_id)
                ->where('tire_id', $data['tire_id'])
                ->where('active', true)
                ->update([
                    'active' =>
                        false,

                    'removed_at' =>
                        now()->toDateString(),

                    'removed_km' =>
                        $vehicle->current_km,

                    'removal_reason' =>
                        'Movimentação para outra posição',
                ]);

            $tire =
                Tire::where('tenant_id', $vehicle->tenant_id)
                    ->withCurrentTreadContext()
                    ->where('id', $data['tire_id'])
                    ->firstOrFail();
            
            $installedKm =
                $data['installed_km'] ?? $vehicle->current_km ?? 0;
            
            $installedAt =
                $data['installed_at'] ?? now()->toDateString();
            
            TireInstallation::create([
                'tenant_id' =>
                    $vehicle->tenant_id,
            
                'tire_id' =>
                    $tire->id,
            
                'vehicle_id' =>
                    $vehicle->id,
            
                'position_code' =>
                    $data['position_code'],
            
                'installed_at' =>
                    $installedAt,
            
                'installed_km' =>
                    $installedKm,
            
                'active' =>
                    true,
            
                'created_by' =>
                    $authUser->id,
            ]);
            
            $tire->update([
                'status' =>
                    'installed',
            ]);
            
            /*
            |--------------------------------------------------------------------------
            | MEDIÇÃO INICIAL AUTOMÁTICA
            |--------------------------------------------------------------------------
            | Ao instalar o pneu, o sulco inicial cadastrado passa a ser a primeira
            | medição da posição. Assim o sistema não gera alerta imediatamente.
            */
            
            if ($tire->current_tread_depth !== null) {
            
                $initialTread =
                    round(
                        (float) $tire->current_tread_depth,
                        2
                    );
            
                TireMeasurement::create([
                    'tenant_id' =>
                        $vehicle->tenant_id,
            
                    'tire_id' =>
                        $tire->id,
            
                    'vehicle_id' =>
                        $vehicle->id,
            
                    'position_code' =>
                        $data['position_code'],
            
                    'measured_at' =>
                        $installedAt,
            
                    'vehicle_km' =>
                        $installedKm,
            
                    'outer_tread' =>
                        $initialTread,
            
                    'center_outer_tread' =>
                        null,
            
                    'center_inner_tread' =>
                        null,
            
                    'inner_tread' =>
                        null,
            
                    'average_tread' =>
                        $initialTread,
            
                    'minimum_tread' =>
                        $initialTread,
            
                    'notes' =>
                        'Medição inicial automática registrada na instalação do pneu.',
            
                    'user_id' =>
                        $authUser->id,
                ]);
            }
        });

        return back()->with(
            'success',
            'Pneu instalado na posição.'
        );
    }

    public function storeMeasurement(
        Request $request,
        Vehicle $vehicle
    ) {
        if ($redirect = $this->ensureVehicleInActiveContext($vehicle)) {
            return $redirect;
        }

        $authUser =
            auth()->user();
    
        if (
            $authUser->id !== 1
            &&
            (int) $vehicle->tenant_id !== (int) $authUser->tenant_id
        ) {
            abort(403);
        }
    
        $data =
            $request->validate([
                'position_code' => [
                    'required',
                    'string',
                    'max:20',
                ],
    
                'tire_id' => [
                    'required',
                    'exists:tires,id',
                ],
    
                'vehicle_km' => [
                    'required',
                    'numeric',
                    'min:0',
                ],
    
                'current_tread' => [
                    'required',
                    'numeric',
                    'min:0',
                    'max:50',
                ],
    
                'notes' => [
                    'nullable',
                    'string',
                ],
            ]);
    
        $oldKm =
            $vehicle->current_km;
    
        if (
            $oldKm !== null
            &&
            (float) $data['vehicle_km'] < (float) $oldKm
        ) {
            return back()
                ->withErrors([
                    'vehicle_km' =>
                        'O KM informado não pode ser menor que o hodômetro atual do veículo.',
                ])
                ->withInput();
        }
    
        DB::transaction(function () use (
            $data,
            $vehicle,
            $authUser,
            $oldKm
        ) {
    
            $currentTread =
                round(
                    (float) $data['current_tread'],
                    2
                );
    
            /*
            |--------------------------------------------------------------------------
            | REGISTRA MEDIÇÃO DE SULCO
            |--------------------------------------------------------------------------
            */
    
            TireMeasurement::create([
                'tenant_id' =>
                    $vehicle->tenant_id,
    
                'tire_id' =>
                    $data['tire_id'],
    
                'vehicle_id' =>
                    $vehicle->id,
    
                'position_code' =>
                    $data['position_code'],
    
                'measured_at' =>
                    now()->toDateString(),
    
                'vehicle_km' =>
                    $data['vehicle_km'],
    
                /*
                |--------------------------------------------------------------------------
                | MVP: medição simples
                |--------------------------------------------------------------------------
                | Usamos outer_tread como campo principal informado.
                | Average e minimum recebem o mesmo valor para manter alertas/status.
                */
    
                'outer_tread' =>
                    $currentTread,
    
                'center_outer_tread' =>
                    null,
    
                'center_inner_tread' =>
                    null,
    
                'inner_tread' =>
                    null,
    
                'average_tread' =>
                    $currentTread,
    
                'minimum_tread' =>
                    $currentTread,
    
                'notes' =>
                    $data['notes'] ?? null,
    
                'user_id' =>
                    $authUser->id,
            ]);
    
            /*
            |--------------------------------------------------------------------------
            | ATUALIZA HODÔMETRO DO VEÍCULO, SE NECESSÁRIO
            |--------------------------------------------------------------------------
            */
    
            if (
                $oldKm === null
                ||
                (float) $data['vehicle_km'] > (float) $oldKm
            ) {
                $vehicle->update([
                    'current_km' =>
                        $data['vehicle_km'],
    
                    'last_km_update_at' =>
                        now(),
                ]);
    
                VehicleUpdateLog::create([
                
                    'vehicle_id' =>
                        $vehicle->id,
                
                    'user_id' =>
                        $authUser->id,
                
                    'division_id' =>
                        $vehicle->division_id,
                
                    'location_id' =>
                        $vehicle->location_id,
                
                    'type' =>
                        'km',
                
                    'source' =>
                        'tire_measurement',
                
                    'old_value' =>
                        $oldKm,
                
                    'new_value' =>
                        $data['vehicle_km'],
                
                    'observation' =>
                        'Hodômetro atualizado automaticamente a partir de medição de pneu.',
                
                ]);
            }
        });
    
        return back()->with(
            'success',
            'Medição de pneu registrada.'
        );
    }

    private function createDefaultPositions(Vehicle $vehicle): void
    {
        $positions =
            $this->positionsForLayout(
                $vehicle->tire_layout
            );
    
        foreach ($positions as $position) {
            VehicleTirePosition::firstOrCreate(
                [
                    'vehicle_id' =>
                        $vehicle->id,
    
                    'code' =>
                        $position['code'],
                ],
                [
                    'tenant_id' =>
                        $vehicle->tenant_id,
    
                    'label' =>
                        $position['label'],
    
                    'sort_order' =>
                        $position['sort_order'],
    
                    'active' =>
                        true,
                ]
            );
        }
    }
    private function positionsForLayout(?string $layout): array
    {
        return match ($layout) {
    
            /*
            |--------------------------------------------------------------------------
            | VEÍCULO LEVE - 4 PNEUS SIMPLES
            |--------------------------------------------------------------------------
            */
    
            'car_4_single' => [
                [
                    'code' => '1E',
                    'label' => 'Dianteiro esquerdo',
                    'sort_order' => 1,
                ],
                [
                    'code' => '1D',
                    'label' => 'Dianteiro direito',
                    'sort_order' => 2,
                ],
                [
                    'code' => '2E',
                    'label' => 'Traseiro esquerdo',
                    'sort_order' => 3,
                ],
                [
                    'code' => '2D',
                    'label' => 'Traseiro direito',
                    'sort_order' => 4,
                ],
            ],
    
            /*
            |--------------------------------------------------------------------------
            | CAMINHÃO 6 PNEUS - FRENTE SIMPLES / TRASEIRA DUPLA
            |--------------------------------------------------------------------------
            */
    
            'truck_6_mixed' => [
                [
                    'code' => '1E',
                    'label' => 'Dianteiro esquerdo',
                    'sort_order' => 1,
                ],
                [
                    'code' => '1D',
                    'label' => 'Dianteiro direito',
                    'sort_order' => 2,
                ],
                [
                    'code' => '2EI',
                    'label' => 'Traseiro esquerdo interno',
                    'sort_order' => 3,
                ],
                [
                    'code' => '2EE',
                    'label' => 'Traseiro esquerdo externo',
                    'sort_order' => 4,
                ],
                [
                    'code' => '2DI',
                    'label' => 'Traseiro direito interno',
                    'sort_order' => 5,
                ],
                [
                    'code' => '2DE',
                    'label' => 'Traseiro direito externo',
                    'sort_order' => 6,
                ],
            ],
    
            'truck_8_mixed' => [
            [
                'code' => '1E',
                'label' => 'Dianteiro esquerdo',
                'sort_order' => 1,
            ],
            [
                'code' => '1D',
                'label' => 'Dianteiro direito',
                'sort_order' => 2,
            ],
            [
                'code' => '2E',
                'label' => '2º eixo esquerdo',
                'sort_order' => 3,
            ],
            [
                'code' => '2D',
                'label' => '2º eixo direito',
                'sort_order' => 4,
            ],
            [
                'code' => '3EI',
                'label' => '3º eixo esquerdo interno',
                'sort_order' => 5,
            ],
            [
                'code' => '3EE',
                'label' => '3º eixo esquerdo externo',
                'sort_order' => 6,
            ],
            [
                'code' => '3DI',
                'label' => '3º eixo direito interno',
                'sort_order' => 7,
            ],
            [
                'code' => '3DE',
                'label' => '3º eixo direito externo',
                'sort_order' => 8,
            ],
        ],
            /*
            |--------------------------------------------------------------------------
            | CAMINHÃO 10 PNEUS - FRENTE SIMPLES / DOIS EIXOS TRASEIROS DUPLOS
            |--------------------------------------------------------------------------
            */
    
            'truck_10_mixed' => [
                [
                    'code' => '1E',
                    'label' => 'Dianteiro esquerdo',
                    'sort_order' => 1,
                ],
                [
                    'code' => '1D',
                    'label' => 'Dianteiro direito',
                    'sort_order' => 2,
                ],
                [
                    'code' => '2EI',
                    'label' => '2º eixo esquerdo interno',
                    'sort_order' => 3,
                ],
                [
                    'code' => '2EE',
                    'label' => '2º eixo esquerdo externo',
                    'sort_order' => 4,
                ],
                [
                    'code' => '2DI',
                    'label' => '2º eixo direito interno',
                    'sort_order' => 5,
                ],
                [
                    'code' => '2DE',
                    'label' => '2º eixo direito externo',
                    'sort_order' => 6,
                ],
                [
                    'code' => '3EI',
                    'label' => '3º eixo esquerdo interno',
                    'sort_order' => 7,
                ],
                [
                    'code' => '3EE',
                    'label' => '3º eixo esquerdo externo',
                    'sort_order' => 8,
                ],
                [
                    'code' => '3DI',
                    'label' => '3º eixo direito interno',
                    'sort_order' => 9,
                ],
                [
                    'code' => '3DE',
                    'label' => '3º eixo direito externo',
                    'sort_order' => 10,
                ],
            ],
            
             /*
            |--------------------------------------------------------------------------
            | CAMINHÃO 12 PNEUS - FRENTE SIMPLES / TRÊS EIXOS TRASEIROS DUPLOS
            |--
            */
            'truck_12_mixed' => [
            [
                'code' => '1E',
                'label' => 'Dianteiro esquerdo',
                'sort_order' => 1,
            ],
            [
                'code' => '1D',
                'label' => 'Dianteiro direito',
                'sort_order' => 2,
            ],
            [
                'code' => '2EI',
                'label' => '2º eixo esquerdo interno',
                'sort_order' => 3,
            ],
            [
                'code' => '2EE',
                'label' => '2º eixo esquerdo externo',
                'sort_order' => 4,
            ],
            [
                'code' => '2DI',
                'label' => '2º eixo direito interno',
                'sort_order' => 5,
            ],
            [
                'code' => '2DE',
                'label' => '2º eixo direito externo',
                'sort_order' => 6,
            ],
            [
                'code' => '3EI',
                'label' => '3º eixo esquerdo interno',
                'sort_order' => 7,
            ],
            [
                'code' => '3EE',
                'label' => '3º eixo esquerdo externo',
                'sort_order' => 8,
            ],
            [
                'code' => '3DI',
                'label' => '3º eixo direito interno',
                'sort_order' => 9,
            ],
            [
                'code' => '3DE',
                'label' => '3º eixo direito externo',
                'sort_order' => 10,
            ],
            [
                'code' => '4E',
                'label' => '4º eixo esquerdo',
                'sort_order' => 11,
            ],
            [
                'code' => '4D',
                'label' => '4º eixo direito',
                'sort_order' => 12,
            ],
        ],
    
            /*
            |--------------------------------------------------------------------------
            | PADRÃO ATUAL - CASO NÃO DEFINIDO
            |--------------------------------------------------------------------------
            */
    
            default => [
                [
                    'code' => '1E',
                    'label' => 'Dianteiro esquerdo',
                    'sort_order' => 1,
                ],
                [
                    'code' => '1D',
                    'label' => 'Dianteiro direito',
                    'sort_order' => 2,
                ],
                [
                    'code' => '2EI',
                    'label' => 'Traseiro esquerdo interno',
                    'sort_order' => 3,
                ],
                [
                    'code' => '2EE',
                    'label' => 'Traseiro esquerdo externo',
                    'sort_order' => 4,
                ],
                [
                    'code' => '2DI',
                    'label' => 'Traseiro direito interno',
                    'sort_order' => 5,
                ],
                [
                    'code' => '2DE',
                    'label' => 'Traseiro direito externo',
                    'sort_order' => 6,
                ],
            ],
        };
    }
    private function positionStatus(
        ?TireInstallation $installation,
        ?TireMeasurement $measurement
    ): string {
        if (! $installation) {
            return 'empty';
        }
    
        if (! $measurement) {
            return 'pending';
        }
    
        $tire =
            $installation->tire;
    
        $currentTread =
            $measurement->minimum_tread !== null
                ? (float) $measurement->minimum_tread
                : null;
    
        if ($currentTread === null) {
            return 'pending';
        }
    
        $criticalLimit =
            $tire?->critical_tread_depth !== null
                ? (float) $tire->critical_tread_depth
                : 3.00;
    
        $warningLimit =
            $tire?->warning_tread_depth !== null
                ? (float) $tire->warning_tread_depth
                : 5.00;
    
        if ($currentTread <= $criticalLimit) {
            return 'danger';
        }
    
        if ($currentTread <= $warningLimit) {
            return 'warning';
        }
    
        return 'ok';
    }
    
    private function treadWearPercent($tire): ?float
    {
        if (
            ! $tire
            || $tire->tread_reference_depth === null
            || $tire->current_tread_depth === null
            || (float) $tire->tread_reference_depth <= 0
        ) {
            return null;
        }
    
        $initial =
            (float) $tire->tread_reference_depth;
    
        $current =
            (float) $tire->current_tread_depth;
    
        $wear =
            (($initial - $current) / $initial) * 100;
    
        return round(
            max(0, min(100, $wear)),
            1
        );
    }
    public function report(Vehicle $vehicle)
    {
        if ($redirect = $this->ensureVehicleInActiveContext($vehicle)) {
            return $redirect;
        }

        $authUser =
            auth()->user();
    
        if (
            $authUser->id !== 1
            &&
            (int) $vehicle->tenant_id !== (int) $authUser->tenant_id
        ) {
            abort(403);
        }
    
        $divisionId =
            session('active_division_id');
    
        $division =
        Division::find($divisionId);
        
        $vehicle->load([
            'tenant',
            'division',
            'location',
            'tirePositions',
            'tireInstallations.tire' =>
                fn ($query) => $query
                    ->withCurrentTreadContext()
                    ->withCount('retreads'),
            'tireInstallations.creator',
        ]);
    
        $positions =
            $vehicle
                ->tirePositions()
                ->where('active', true)
                ->orderBy('sort_order')
                ->get()
                ->map(function ($position) use ($vehicle) {
    
                    $installation =
                        $vehicle
                            ->tireInstallations
                            ->where('active', true)
                            ->firstWhere(
                                'position_code',
                                $position->code
                            );
    
                    $latestMeasurement =
                        $installation
                            ? TireMeasurement::where('tenant_id', $vehicle->tenant_id)
                                ->where('vehicle_id', $vehicle->id)
                                ->where('tire_id', $installation->tire_id)
                                ->where('position_code', $position->code)
                                ->latest('measured_at')
                                ->latest('id')
                                ->first()
                            : null;
    
                    return [
                        'position' =>
                            $position,
    
                        'installation' =>
                            $installation,
    
                        'tire' =>
                            $installation?->tire,
    
                        'latest_measurement' =>
                            $latestMeasurement,
                        'wear_percent' =>
                        $this->treadWearPercent(
                            $installation?->tire,
                        ),
                        'status' =>
                            $this->positionStatus(
                                $installation,
                                $latestMeasurement
                            ),
                    ];
                });
    
        $measurements =
            TireMeasurement::with([
                    'tire',
                    'user',
                ])
                ->where('tenant_id', $vehicle->tenant_id)
                ->where('vehicle_id', $vehicle->id)
                ->latest('measured_at')
                ->latest('id')
                ->limit(100)
                ->get();
    
        $installations =
            $vehicle
                ->tireInstallations()
                ->with([
                    'tire',
                    'creator',
                ])
                ->latest('installed_at')
                ->latest('id')
                ->get();
    
        $summary = [
            'positions' =>
                $positions->count(),
    
            'installed' =>
                $positions->whereNotNull('tire')->count(),
    
            'without_measurement' =>
                $positions
                    ->filter(fn ($item) =>
                        $item['tire'] && ! $item['latest_measurement']
                    )
                    ->count(),
    
            'warning' =>
                $positions
                    ->where('status', 'warning')
                    ->count(),
    
            'danger' =>
                $positions
                    ->where('status', 'danger')
                    ->count(),
        ];
    
        $pdf =
            Pdf::loadView(
                'vehicle.tires.report',
                compact(
                    'vehicle',
                    'positions',
                    'measurements',
                    'installations',
                    'summary',
                    'division'
                )
            )
                ->setPaper('a4', 'portrait');
    
        return $pdf->stream(
            'relatorio-pneus-' . $vehicle->plate . '.pdf'
        );
    }

    public function removeInstallation(
        Request $request,
        Vehicle $vehicle
    ) {
        if ($redirect = $this->ensureVehicleInActiveContext($vehicle)) {
            return $redirect;
        }

        $authUser =
            auth()->user();
    
        if (
            $authUser->id !== 1
            &&
            (int) $vehicle->tenant_id !== (int) $authUser->tenant_id
        ) {
            abort(403);
        }
    
        $data =
            $request->validate([
                'position_code' => [
                    'required',
                    'string',
                    'max:20',
                ],
    
                'tire_id' => [
                    'required',
                    'exists:tires,id',
                ],
    
                'removed_km' => [
                    'required',
                    'numeric',
                    'min:0',
                ],
    
                'destination' => [
                    'required',
                    'string',
                    'in:available,maintenance,discarded',
                ],
    
                'removal_reason' => [
                    'required',
                    'string',
                    'max:100',
                ],
    
                'notes' => [
                    'nullable',
                    'string',
                ],
            ]);
    
        $oldKm =
            $vehicle->current_km;
    
        if (
            $oldKm !== null
            &&
            (float) $data['removed_km'] < (float) $oldKm
        ) {
            return back()
                ->withErrors([
                    'removed_km' =>
                        'O KM de remoção não pode ser menor que o hodômetro atual do veículo.',
                ])
                ->withInput();
        }
    
        DB::transaction(function () use (
            $data,
            $vehicle,
            $authUser,
            $oldKm
        ) {
            $installation =
                TireInstallation::where('tenant_id', $vehicle->tenant_id)
                    ->where('vehicle_id', $vehicle->id)
                    ->where('position_code', $data['position_code'])
                    ->where('tire_id', $data['tire_id'])
                    ->where('active', true)
                    ->firstOrFail();
    
            $installation->update([
                'active' =>
                    false,
    
                'removed_at' =>
                    now()->toDateString(),
    
                'removed_km' =>
                    $data['removed_km'],
    
                'removal_reason' =>
                    $data['removal_reason'],
            ]);
    
            Tire::where('id', $data['tire_id'])
                ->where('tenant_id', $vehicle->tenant_id)
                ->update([
                    'status' =>
                        $data['destination'],
    
                    'notes' =>
                        $data['notes'] ?? null,
                ]);
    
            if (
                $oldKm === null
                ||
                (float) $data['removed_km'] > (float) $oldKm
            ) {
                $vehicle->update([
                    'current_km' =>
                        $data['removed_km'],
    
                    'last_km_update_at' =>
                        now(),
                ]);
    
                VehicleUpdateLog::create([
                    'vehicle_id' =>
                        $vehicle->id,
    
                    'user_id' =>
                        $authUser->id,
    
                    'division_id' =>
                        $vehicle->division_id,
    
                    'location_id' =>
                        $vehicle->location_id,
    
                    'type' =>
                        'km',
    
                    'source' =>
                        'tire_removal',
    
                    'old_value' =>
                        $oldKm,
    
                    'new_value' =>
                        $data['removed_km'],
    
                    'observation' =>
                        'Hodômetro atualizado automaticamente a partir da remoção/troca de pneu.',
                ]);
            }
        });
    
        return back()->with(
            'success',
            'Pneu removido da posição.'
        );
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

}
