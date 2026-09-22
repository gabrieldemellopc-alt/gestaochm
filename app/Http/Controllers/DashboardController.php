<?php







namespace App\Http\Controllers;



use App\Models\FuelTank;



use App\Models\Procedure;



use App\Models\StockItem;



use App\Models\Vehicle;



use App\Services\PreventiveService;



use App\Services\StockService;



use App\Models\User;



use App\Models\VehicleOperation;



use App\Models\UserDivisionAccess;

use App\Services\ActiveContextService;
use App\Services\AlertService;
use App\Services\OperationalDashboardService;
use Illuminate\Http\Request;




class DashboardController extends Controller



{



    public function index(Request $request)



    {







        /*



        |--------------------------------------------------------------------------



        | DIVISÃO ATIVA



        |--------------------------------------------------------------------------



        */







        if (!session('active_division_id')) {







            return redirect()







                ->route('portal')







                ->with(







                    'warning',







                    'Selecione uma divisão para continuar.'







                );



        }



        /*



        |--------------------------------------------------------------------------



        | VEÍCULOS



        |--------------------------------------------------------------------------



        */







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



        $allowedFleetRelations = [
            Vehicle::FLEET_RELATION_INTERNAL,
            Vehicle::FLEET_RELATION_AGGREGATED,
            Vehicle::FLEET_RELATION_RENTED,
            'all',
        ];

        $fleetRelationCookie = 'chm_fleet_relation_'.$activeLocation->id;

        if ($request->has('fleet_relation')) {
            $fleetRelation = (string) $request->query('fleet_relation');

            abort_unless(
                in_array($fleetRelation, $allowedFleetRelations, true),
                404
            );

            cookie()->queue(
                cookie(
                    $fleetRelationCookie,
                    $fleetRelation,
                    0
                )
            );
        } else {
            $savedFleetRelation = $request->cookie($fleetRelationCookie);

            $fleetRelation = in_array(
                $savedFleetRelation,
                $allowedFleetRelations,
                true
            )
                ? $savedFleetRelation
                : Vehicle::FLEET_RELATION_INTERNAL;
        }
        $vehicles = Vehicle::with([





            'maintenances.procedure',



            'procedures',







            'activeMaintenances.procedure',







            'updateLogs.user',







            'currentAllocation.location',







            'currentAllocation.division',



            'openOperation.driver',







        ])



        ->where(







            'division_id',







            session('active_division_id')







        )

        ->where('tenant_id', auth()->user()->tenant_id)

        ->where('location_id', $activeLocation->id)
        ->where('status', 'active')
        ->when($fleetRelation !== 'all', fn ($query) => $query->where('fleet_relation', $fleetRelation))

        ->latest()

        ->get();

        $canViewDashboardMaintenanceCosts = app(
            \App\Services\Permissions\ProfilePermissionService::class
        )->allows(
            auth()->user(),
            'maintenance.view_costs',
            [
                'division_id' => session('active_division_id'),
                'location_id' => $activeLocation->id,
                'module' => 'fleet',
            ]
        );

        $vehicles->each(function (Vehicle $vehicle) use ($canViewDashboardMaintenanceCosts): void {
            $vehicle->setAttribute(
                'icon_url',
                asset('images/'.Vehicle::iconForType($vehicle->type))
            );

            $fuelTrend = \App\Models\FuelFilling::query()
                ->where('vehicle_id', $vehicle->id)
                ->whereNull('cancelled_at')
                ->whereNotNull('quantity_liters')
                ->orderByDesc('filled_at')
                ->limit(10)
                ->get([
                    'id',
                    'filled_at',
                    'quantity_liters',
                ])
                ->sortBy('filled_at')
                ->values()
                ->map(fn ($filling) => [
                    'id' => $filling->id,
                    'date' => $filling->filled_at?->format('d/m'),
                    'datetime' => $filling->filled_at?->format('d/m/Y H:i'),
                    'liters' => round((float) $filling->quantity_liters, 3),
                ])
                ->values()
                ->all();

            $kmReadings = \App\Models\FuelFilling::query()
                ->where('vehicle_id', $vehicle->id)
                ->whereNull('cancelled_at')
                ->whereNotNull('vehicle_km')
                ->orderByDesc('filled_at')
                ->limit(11)
                ->get([
                    'id',
                    'filled_at',
                    'vehicle_km',
                ])
                ->sortBy('filled_at')
                ->values();

            $kmTrend = $kmReadings
                ->map(function ($filling, $index) use ($kmReadings) {

                    if ($index === 0) {
                        return null;
                    }

                    $previous = $kmReadings->get($index - 1);

                    $currentKm = (float) $filling->vehicle_km;
                    $previousKm = (float) $previous->vehicle_km;

                    $distance = $currentKm - $previousKm;

                    /*
                     * Regressões não entram como "rodagem".
                     * Elas já são tratadas pela Central de Consistência.
                     */
                    if ($distance < 0) {
                        return null;
                    }

                    return [
                        'id' => $filling->id,
                        'label' => optional($filling->filled_at)->format('d/m'),
                        'value' => $distance,
                        'formatted_value' => number_format(
                            $distance,
                            0,
                            ',',
                            '.'
                        ) . ' km',
                        'date' => optional($filling->filled_at)
                            ->format('d/m/Y H:i'),
                        'current_km' => $currentKm,
                        'previous_km' => $previousKm,

                        /*
                         * Duração real entre as duas leituras.
                         * Usamos minutos para não perder intervalos
                         * menores que 24 horas.
                         */
                        'interval_days' => max(
                            $previous->filled_at->diffInMinutes(
                                $filling->filled_at
                            ) / 1440,
                            1 / 1440
                        ),
                    ];

                })
                ->filter()
                ->values()
                ->take(-10)
                ->values();
            /*
             * Evolução KM/L
             *
             * Cada ponto compara um abastecimento com o anterior:
             *
             * (KM atual - KM anterior) / litros do abastecimento atual
             *
             * Leituras regressivas, zeradas ou marcadas como suspeitas
             * não entram no gráfico.
             */
            $efficiencyReadings = \App\Models\FuelFilling::query()
                ->where('vehicle_id', $vehicle->id)
                ->whereNull('cancelled_at')
                ->whereNotNull('vehicle_km')
                ->whereNotNull('quantity_liters')
                ->where('quantity_liters', '>', 0)
                ->orderByDesc('filled_at')
                ->limit(11)
                ->get([
                    'id',
                    'filled_at',
                    'vehicle_km',
                    'vehicle_km_status',
                    'quantity_liters',
                ])
                ->sortBy('filled_at')
                ->values();

            $efficiencyTrend = $efficiencyReadings
                ->map(function ($filling, $index) use ($efficiencyReadings) {

                    if ($index === 0) {
                        return null;
                    }

                    $previous = $efficiencyReadings->get($index - 1);

                    if (
                        $filling->vehicle_km_status === 'suspect'
                        || $previous->vehicle_km_status === 'suspect'
                    ) {
                        return null;
                    }

                    $currentKm = (float) $filling->vehicle_km;
                    $previousKm = (float) $previous->vehicle_km;

                    $distance = $currentKm - $previousKm;
                    $liters = (float) $filling->quantity_liters;

                    if ($distance <= 0 || $liters <= 0) {
                        return null;
                    }

                    $efficiency = $distance / $liters;

                    return [
                        'id' => $filling->id,

                        'label' => optional($filling->filled_at)
                            ->format('d/m'),

                        'date' => optional($filling->filled_at)
                            ->format('d/m/Y H:i'),

                        'value' => round($efficiency, 4),

                        'formatted_value' => number_format(
                            $efficiency,
                            2,
                            ',',
                            '.'
                        ) . ' km/L',

                        'distance' => $distance,
                        'liters' => $liters,

                        'current_km' => $currentKm,
                        'previous_km' => $previousKm,
                    ];

                })
                ->filter()
                ->values()
                ->take(-10)
                ->values();

            $vehicle->setAttribute('fuel_trend', $fuelTrend);
            $vehicle->setAttribute('km_trend', $kmTrend);
            $vehicle->setAttribute('efficiency_trend', $efficiencyTrend);

            $vehicle->setAttribute(
                'recent_maintenances_modal',
                $vehicle->maintenances
                    ->filter(fn ($maintenance) =>
                        $maintenance->cancelled_at === null
                        && $maintenance->deleted_at === null
                    )
                    ->sortByDesc(fn ($maintenance) =>
                        $maintenance->started_at
                        ?? $maintenance->created_at
                    )
                    ->take(3)
                    ->map(function ($maintenance) use ($vehicle, $canViewDashboardMaintenanceCosts) {

                        $reasonLabel = match ($maintenance->reason) {
                            'corrective' => 'Corretiva',
                            'preventive' => 'Preventiva',
                            'inspection' => 'Inspeção',
                            'accident' => 'Sinistro',
                            default => filled($maintenance->reason)
                                ? ucfirst(str_replace('_', ' ', $maintenance->reason))
                                : 'Manutenção',
                        };

                        $workflowLabel = match ($maintenance->workflow_status) {
                            'open' => 'Em aberto',
                            'closed' => 'Encerrada',
                            'cancelled' => 'Cancelada',
                            default => ucfirst((string) $maintenance->workflow_status),
                        };

                        $serviceStatuses = \App\Services\MaintenanceService::serviceStatuses();

                        return [
                            'id' => $maintenance->id,

                            'name' => $maintenance->procedure?->name
                                ?: 'Ordem de manutenção #'.$maintenance->id,

                            'reason' => $reasonLabel,

                            'workflow_status' => $maintenance->workflow_status,
                            'workflow_label' => $workflowLabel,

                            'service_status' => $maintenance->service_status,
                            'service_status_label' =>
                                $serviceStatuses[$maintenance->service_status]
                                ?? null,

                            'started_at' => optional(
                                $maintenance->started_at
                                ?? $maintenance->created_at
                            )->format('d/m/Y H:i'),

                            'finished_at' => optional(
                                $maintenance->finished_at
                            )->format('d/m/Y H:i'),

                            'total_cost' => $canViewDashboardMaintenanceCosts
                                ? (float) ($maintenance->total_cost ?? 0)
                                : null,

                            'can_view_cost' => $canViewDashboardMaintenanceCosts,

                            'url' => route(
                                'vehicles.maintenance.show',
                                [$vehicle->id, $maintenance->id]
                            ),
                        ];
                    })
                    ->values()
                    ->all()
            );
        });







        /*



        |--------------------------------------------------------------------------



        | ALERTAS / STATUS / RESUMOS



        |--------------------------------------------------------------------------



        */



        $statusDefinitions = [



            'operational' => [

                'label' => 'Operacionais',

                'card_label' => 'Operacional',

                'icon' => 'circle-check',

                'tone' => 'success',

                'order' => 10,

            ],



            'maintenance' => [

                'label' => 'Em manutenção',

                'card_label' => 'Manutenção',

                'icon' => 'wrench',

                'tone' => 'maintenance',

                'order' => 20,

            ],



            'inactive' => [

                'label' => 'Inativos',

                'card_label' => 'Inativo',

                'icon' => 'circle-off',

                'tone' => 'neutral',

                'order' => 30,

            ],



            'inoperant' => [

                'label' => 'Inoperantes',

                'card_label' => 'Inoperante',

                'icon' => 'octagon-x',

                'tone' => 'neutral',

                'order' => 40,

            ],



            'accident' => [

                'label' => 'Em sinistro',

                'card_label' => 'Sinistro',

                'icon' => 'triangle-alert',

                'tone' => 'neutral',

                'order' => 50,

            ],



            'support' => [

                'label' => 'Em socorro',

                'card_label' => 'Socorro',

                'icon' => 'ambulance',

                'tone' => 'neutral',

                'order' => 60,

            ],



            'testing' => [

                'label' => 'Em testes',

                'card_label' => 'Em testes',

                'icon' => 'flask-conical',

                'tone' => 'neutral',

                'order' => 70,

            ],



            'transfer' => [

                'label' => 'Em transferência',

                'card_label' => 'Transferência',

                'icon' => 'arrow-right-left',

                'tone' => 'neutral',

                'order' => 80,

            ],



            'transferred' => [

                'label' => 'Transferidos',

                'card_label' => 'Transferido',

                'icon' => 'truck',

                'tone' => 'neutral',

                'order' => 90,

            ],



        ];



        foreach ($vehicles as $vehicle) {



            $vehicle->open_maintenance = $vehicle->maintenances

                ->first(function ($maintenance) {

                    return

                        $maintenance->workflow_status === 'open'

                        && $maintenance->cancelled_at === null

                        && $maintenance->deleted_at === null;

                });



            // if ($vehicle->open_maintenance) {

            //     $vehicle->operational_status = 'maintenance';

            // }



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





            /*



            |--------------------------------------------------------------------------



            | DATA FORMATADA



            |--------------------------------------------------------------------------



            */







            $vehicle->last_maintenance_date =



                optional(



                    $vehicle->last_maintenance?->performed_at



                )



                ?->format('d/m/Y');







            $vehicle->alert_status =



                PreventiveService::getVehicleStatus($vehicle);







            /*

            |--------------------------------------------------------------------------

            | NORMALIZAÇÃO DO STATUS OPERACIONAL

            |--------------------------------------------------------------------------

            */



            if ($vehicle->open_maintenance) {



                $vehicle->operational_status = 'maintenance';



            } elseif (!$vehicle->operational_status) {



                /*

                * Compatibilidade com o campo legado "status".

                *

                * Caso o veículo esteja marcado como inativo no campo geral,

                * mas ainda não tenha operational_status, consideramos "inactive".

                */



                $vehicle->operational_status =

                    $vehicle->status === 'inactive'

                        ? 'inactive'

                        : 'operational';

            }



            $vehicle->operational_status = strtolower(

                trim($vehicle->operational_status)

            );



            /*

            * Proteção para algum status novo que futuramente seja cadastrado.

            */

            if (!isset($statusDefinitions[$vehicle->operational_status])) {



                $statusDefinitions[$vehicle->operational_status] = [

                    'label' => str($vehicle->operational_status)

                        ->replace('_', ' ')

                        ->title()

                        ->toString(),



                    'card_label' => str($vehicle->operational_status)

                        ->replace('_', ' ')

                        ->title()

                        ->toString(),



                    'icon' => 'circle-dot',

                    'tone' => 'muted',

                    'order' => 999,

                ];

            }



            $vehicleStatusDefinition =

                $statusDefinitions[$vehicle->operational_status];



            $vehicle->operational_status_label =

                $vehicleStatusDefinition['card_label'];



            $vehicle->operational_status_icon =

                $vehicleStatusDefinition['icon'];



            $vehicle->operational_status_tone =

                $vehicleStatusDefinition['tone'];







            $vehicle->open_operation = $vehicle->openOperation;



            $vehicle->operation_location_id =



                $vehicle->currentAllocation?->location_id



                ?? $vehicle->location_id



                ?? null;







            $vehicle->operation_location_name =



                $vehicle->currentAllocation?->location?->name



                ?? $vehicle->location?->name



                ?? null;



            $vehicle->is_in_operation = (bool) $vehicle->open_operation;







            $vehicle->operation_driver_name =



                $vehicle->open_operation?->driver?->name;







            $vehicle->operation_started_at_formatted =



                optional($vehicle->open_operation?->start_datetime_reported)



                    ?->format('d/m/Y H:i');







            $vehicle->operation_started_at_human =



                optional($vehicle->open_operation?->start_datetime_reported)



                    ?->diffForHumans(null, true);



            $openDowntime = \App\Models\VehicleDowntimePeriod::query()

            ->where('vehicle_id', $vehicle->id)

            ->whereNull('ended_at')

            ->latest('started_at')

            ->first();



            $statusChangedDate = $openDowntime?->started_at?->format('d/m/Y')

                ?? $vehicle->status_changed_at?->format('d/m/Y');



            $statusChangedTime = $openDowntime?->started_at?->format('H:i')

                ?? $vehicle->status_changed_at?->format('H:i');



            $currentStatusStartedAt =

                $openDowntime?->started_at

                ?? $vehicle->status_changed_at

                ?? null;



            $currentStatusMinutes = $currentStatusStartedAt

                ? (int) floor(

                    $currentStatusStartedAt->diffInMinutes(now())

                )

                : 0;



            $currentStatusDays = $currentStatusStartedAt

                ? (int) floor(

                    $currentStatusStartedAt

                        ->copy()

                        ->startOfDay()

                        ->diffInDays(now()->startOfDay())

                )

                : null;

            $vehicle->maintenance_stopped_days =

            $vehicle->operational_status === 'maintenance'

                ? $currentStatusDays

                : null;

            $downTimeText = '--';

            $downTimeSubtext = '';



            if ($currentStatusMinutes > 0) {

                $days = floor($currentStatusMinutes / 1440);

                $hours = floor($currentStatusMinutes / 60);

                $minutes = $currentStatusMinutes % 60;



                $downTimeText = $days > 0

                    ? $days.' dia'.($days > 1 ? 's' : '')

                    : ($hours > 0

                        ? $hours.' hora'.($hours > 1 ? 's' : '')

                        : $minutes.' minuto'.($minutes > 1 ? 's' : ''));



                $downTimeSubtext = $hours.'h '.$minutes.'min';

            }



            /*
             * TEMPO PARADO
             *
             * Regra operacional:
             * soma o tempo efetivamente transcorrido em manutenções válidas.
             *
             * - manutenção encerrada: started_at -> finished_at
             * - manutenção aberta: started_at -> agora
             * - canceladas/apagadas não entram
             * - períodos anteriores ao início operacional são limitados
             * - intervalos sobrepostos são fundidos para não contar tempo em dobro
             */
            $operationStartedAt = $vehicle->operation_started_at
                ? $vehicle->operation_started_at->copy()->startOfDay()
                : null;

            $maintenanceIntervals = \App\Models\MaintenanceRecord::query()
                ->where('vehicle_id', $vehicle->id)
                ->whereNull('cancelled_at')
                ->whereNull('deleted_at')
                ->whereNotNull('started_at')
                ->orderBy('started_at')
                ->get([
                    'id',
                    'started_at',
                    'finished_at',
                    'workflow_status',
                ])
                ->map(function ($maintenance) use ($operationStartedAt) {

                    $start = $maintenance->started_at?->copy();

                    if (! $start) {
                        return null;
                    }

                    $end = $maintenance->finished_at
                        ? $maintenance->finished_at->copy()
                        : now();

                    if ($operationStartedAt && $end->lte($operationStartedAt)) {
                        return null;
                    }

                    if ($operationStartedAt && $start->lt($operationStartedAt)) {
                        $start = $operationStartedAt->copy();
                    }

                    if ($end->lte($start)) {
                        return null;
                    }

                    return [
                        'start' => $start,
                        'end' => $end,
                    ];
                })
                ->filter()
                ->values();

            $mergedMaintenanceIntervals = collect();

            foreach ($maintenanceIntervals as $interval) {

                if ($mergedMaintenanceIntervals->isEmpty()) {
                    $mergedMaintenanceIntervals->push($interval);
                    continue;
                }

                $lastIndex = $mergedMaintenanceIntervals->count() - 1;
                $last = $mergedMaintenanceIntervals->get($lastIndex);

                if ($interval['start']->lte($last['end'])) {

                    if ($interval['end']->gt($last['end'])) {
                        $last['end'] = $interval['end'];
                        $mergedMaintenanceIntervals->put($lastIndex, $last);
                    }

                    continue;
                }

                $mergedMaintenanceIntervals->push($interval);
            }

            $totalDowntimeMinutes = (int) $mergedMaintenanceIntervals
                ->sum(
                    fn ($interval) =>
                        floor(
                            $interval['start']->diffInMinutes($interval['end'])
                        )
                );




            $totalDowntimeText = '--';

            $totalDowntimeSubtext = '';



            if ($totalDowntimeMinutes > 0) {

                $days = floor($totalDowntimeMinutes / 1440);

                $hours = floor($totalDowntimeMinutes / 60);

                $minutes = $totalDowntimeMinutes % 60;



                $totalDowntimeText = $days > 0

                    ? $days.' dia'.($days > 1 ? 's' : '')

                    : ($hours > 0

                        ? $hours.' hora'.($hours > 1 ? 's' : '')

                        : $minutes.' minuto'.($minutes > 1 ? 's' : ''));



                $totalDowntimeSubtext = $hours.'h '.$minutes.'min acumulados';

            }



            $totalOperationalMinutes = $vehicle->operation_started_at

                ? $vehicle->operation_started_at->diffInMinutes(now())

                : 0;



            $availabilityText = '--';

            $availabilitySubtext = '';



            if ($totalOperationalMinutes > 0) {

                $availableMinutes = max($totalOperationalMinutes - $totalDowntimeMinutes, 0);



                $availabilityRate = round(

                    ($availableMinutes / $totalOperationalMinutes) * 100,

                    1

                );



                $availableDays = floor($availableMinutes / 1440);

                $availableHours = floor($availableMinutes / 60);

                $availableRemainingMinutes = $availableMinutes % 60;



                $availabilityText = $availableDays > 0

                    ? $availableDays.' dia'.($availableDays > 1 ? 's' : '')

                    : ($availableHours > 0

                        ? $availableHours.' hora'.($availableHours > 1 ? 's' : '')

                        : $availableRemainingMinutes.' minuto'.($availableRemainingMinutes > 1 ? 's' : ''));



                $availabilitySubtext = $availableHours.'h '.$availableRemainingMinutes.'min ('.$availabilityRate.'%)';

            }



            $vehicle->status_changed_date = $statusChangedDate;

            $vehicle->status_changed_time = $statusChangedTime;



            $vehicle->down_time_text = $downTimeText;

            $vehicle->down_time_subtext = $downTimeSubtext;



            $vehicle->current_status_days = $currentStatusDays;

            $vehicle->current_status_started_at = $currentStatusStartedAt;



            $vehicle->total_downtime_text = $totalDowntimeText;

            $vehicle->total_downtime_subtext = $totalDowntimeSubtext;



            $vehicle->availability_text = $availabilityText;

            $vehicle->availability_subtext = $availabilitySubtext;



            $vehicle->open_downtime_reason = $openDowntime?->reason;

        }



        /*



        |--------------------------------------------------------------------------



        | PROCEDIMENTOS



        |--------------------------------------------------------------------------



        */







        $procedures = Procedure::with(

            'fields.stockCategory'

        )

            ->where('tenant_id', auth()->user()->tenant_id)

            ->where('location_id', $activeLocation->id)

            ->get();





        /*



        |--------------------------------------------------------------------------



        | ESTOQUE



        |--------------------------------------------------------------------------



        */







        $stockItems = StockItem::where('tenant_id', auth()->user()->tenant_id)

            ->where('location_id', $activeLocation->id)

            ->where('active', true)

            ->get();





        $criticalStockItems = $stockItems

            ->filter(function ($item) {

                return StockService::getStatus($item) === 'danger';

            })

            ->values();



        $warningStockItems = $stockItems

            ->filter(function ($item) {

                return StockService::getStatus($item) === 'warning';

            })

            ->values();



        $criticalStockCount = $criticalStockItems->count();

        $warningStockCount = $warningStockItems->count();



        $lowStockItems = $criticalStockItems

            ->concat($warningStockItems)

            ->values();



        $lowStockCount = $lowStockItems->count();



        /*

        |--------------------------------------------------------------------------

        | TANQUES DE COMBUSTÍVEL

        |--------------------------------------------------------------------------

        */



        $fuelTanks = FuelTank::query()

            ->where('tenant_id', auth()->user()->tenant_id)

            ->where('division_id', session('active_division_id'))

            ->where('location_id', $activeLocation->id)

            ->where('active', true)

            ->with('product')

            ->get();



        $criticalFuelTanks = $fuelTanks

            ->filter(function ($tank) {

                return

                    (float) $tank->current_balance_liters

                    <=

                    (float) $tank->minimum_balance_liters;

            })

            ->values();



        $warningFuelTanks = $fuelTanks

            ->filter(function ($tank) {



                $capacity = (float) $tank->capacity_liters;

                $balance = (float) $tank->current_balance_liters;

                $minimum = (float) $tank->minimum_balance_liters;



                if ($capacity <= 0) {

                    return false;

                }



                /*

                * Críticos já são tratados separadamente.

                */

                if ($balance <= $minimum) {

                    return false;

                }



                $percentage = ($balance / $capacity) * 100;



                return $percentage <= 30;

            })

            ->values();



        $criticalFuelTankCount = $criticalFuelTanks->count();

        $warningFuelTankCount = $warningFuelTanks->count();

        $pendingFuelReceiptInvoiceCount =
            AlertService::getPendingFuelReceiptInvoiceCount(
                (int) auth()->user()->tenant_id,
                (int) session('active_division_id'),
                (int) $activeLocation->id
            );

        /*



        |--------------------------------------------------------------------------



        | KPIs



        |--------------------------------------------------------------------------



        */







        $criticalVehicles =



            $vehicles



            ->where('alert_status', 'danger')



            ->count();







        $warningVehicles =



            $vehicles



            ->where('alert_status', 'warning')



            ->count();







        /*

        |--------------------------------------------------------------------------

        | RESUMO COMPLETO POR STATUS OPERACIONAL

        |--------------------------------------------------------------------------

        */



        $statusSummary = collect($statusDefinitions)



            ->map(function ($definition, $status) use ($vehicles) {



                return [

                    'status' => $status,

                    'label' => $definition['label'],

                    'icon' => $definition['icon'],

                    'tone' => $definition['tone'],

                    'order' => $definition['order'],



                    'count' => $vehicles

                        ->where('operational_status', $status)

                        ->count(),

                ];



            })



            /*

            * No resumo lateral, mostramos apenas situações existentes.

            */

            ->filter(function ($item) {



                return $item['count'] > 0;



            })



            ->sortBy('order')



            ->values();





        /*

        * Mantemos essas variáveis porque outras áreas do dashboard

        * já dependem delas.

        */



        $operationalVehicles = $vehicles

            ->where('operational_status', 'operational')

            ->count();



        $maintenanceVehicles = $vehicles

            ->where('operational_status', 'maintenance')

            ->count();



        $inactiveVehicles = $vehicles

            ->where('operational_status', 'inactive')

            ->count();











        // Nova área lateral direita - Prioridades Operacionais



        $operationalUpdatePendingVehicles =



            $vehicles



                ->filter(function ($vehicle) {



                    return collect($vehicle->alerts ?? [])



                        ->contains(function ($alert) {



                            return



                                isset($alert['procedure'])



                                &&



                                $alert['procedure'] === 'Atualização operacional';



                        });



                })



                ->values();







        $operationalUpdatePendingCount =



            $operationalUpdatePendingVehicles->count();











        $currentUser = auth()->user();







        $activeDivisionId = session('active_division_id');







        $currentAccess = UserDivisionAccess::query()



            ->where('user_id', $currentUser->id)



            ->where('division_id', $activeDivisionId)



            ->where('module', 'fleet')



            ->where('active', 1)



            ->first();







        $userRole = strtolower(



            $currentAccess->profile



            ?? $currentUser->profile



            ?? $currentUser->role



            ?? $currentUser->type



            ?? ''



        );







        $canManageOperationDrivers =



            in_array($userRole, [



                'admin',



                'manager',



                'supervisor',



            ])



            || ($currentUser->level ?? 0) >= 50;







        $cannotStartOperation =



            in_array($userRole, [



                'mechanic',



            ]);







        $operationDrivers = collect();







        if ($canManageOperationDrivers) {







            $driversWithOpenOperations = VehicleOperation::query()



                ->where('status', 'open')



                ->pluck('driver_id')



                ->filter()



                ->unique()



                ->values()



                ->toArray();







            $driverAccesses = UserDivisionAccess::query()



                ->with('user')



                ->where('division_id', $activeDivisionId)



                ->where('module', 'fleet')



                ->where('profile', 'driver')



                ->where('active', 1)



                ->get()



                ->filter(fn ($access) => $access->user);







            $operationDrivers = $driverAccesses



                ->groupBy('user_id')



                ->map(function ($accesses) use ($driversWithOpenOperations) {







                    $firstAccess = $accesses->first();







                    $user = $firstAccess->user;







                    $user->operation_location_ids = $accesses



                        ->pluck('location_id')



                        ->filter()



                        ->unique()



                        ->values()



                        ->implode(',');







                    $user->operation_profile = 'driver';







                    $user->has_open_operation =



                        in_array($user->id, $driversWithOpenOperations);







                    return $user;



                })



                ->sortBy('name')



                ->values();



        }



        $myOpenOperation = VehicleOperation::query()


            ->where('driver_id', auth()->id())



            ->where('status', 'open')



            ->with('vehicle')



            ->first();

        $operationalIndicators = app(OperationalDashboardService::class)
            ->indicators(auth()->user(), $fleetRelation);

        $fuelConsumptionRanking = $operationalIndicators['fuel_consumption_ranking'];
        $vehicleFuelAverages = $operationalIndicators['vehicle_fuel_averages'];
        $longestStoppedVehicles = $operationalIndicators['longest_stopped_vehicles'];
        $sixMonthCostSeries = $operationalIndicators['six_month_cost_series'];
        $canViewDashboardCosts = $operationalIndicators['can_view_dashboard_costs'];








        /*



        |--------------------------------------------------------------------------



        | VIEW



        |--------------------------------------------------------------------------



        */







        return view('dashboard', compact(
            'fleetRelation',







            'vehicles',







            'procedures',







            'stockItems',







            'criticalVehicles',







            'warningVehicles',







            'operationalVehicles',



            'statusSummary',



            'maintenanceVehicles',



            'lowStockItems',



            'lowStockCount',



            'operationalUpdatePendingVehicles',



            'operationalUpdatePendingCount',







            'canManageOperationDrivers',



            'cannotStartOperation',



            'operationDrivers',



            'myOpenOperation',

            'fuelConsumptionRanking',

            'vehicleFuelAverages',

            'longestStoppedVehicles',

            'sixMonthCostSeries',

            'canViewDashboardCosts',


            'criticalFuelTanks',

            'warningFuelTanks',

            'criticalFuelTankCount',

            'warningFuelTankCount',
            'pendingFuelReceiptInvoiceCount',



            'criticalStockItems',

            'warningStockItems',

            'criticalStockCount',

            'warningStockCount',



            'inactiveVehicles',





        ));



    }



}
