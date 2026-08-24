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



        $fleetRelation = $request->query('fleet_relation', Vehicle::FLEET_RELATION_INTERNAL);
        abort_unless(in_array($fleetRelation, [Vehicle::FLEET_RELATION_INTERNAL, Vehicle::FLEET_RELATION_AGGREGATED, 'all'], true), 404);
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
        ->when($fleetRelation !== 'all', fn ($query) => $query->where('fleet_relation', $fleetRelation))

        ->latest()

        ->get();







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

    

            $totalDowntimeMinutes = \App\Models\VehicleDowntimePeriod::query()

                ->where('vehicle_id', $vehicle->id)

                ->get()

                ->sum(function ($period) {

                    $end = $period->ended_at ?? now();

            

                    return $period->started_at

                        ? $period->started_at->diffInMinutes($end)

                        : 0;

                });

            

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
            ->indicators(auth()->user());

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



            'criticalStockItems',

            'warningStockItems',

            'criticalStockCount',

            'warningStockCount',



            'inactiveVehicles',





        ));



    }



}
