<?php

namespace App\Http\Controllers;

use App\Models\Procedure;
use App\Models\StockItem;
use App\Models\Vehicle;
use App\Services\PreventiveService;
use App\Services\StockService;
use App\Models\User;
use App\Models\VehicleOperation;
use App\Models\UserDivisionAccess;
use App\Services\ActiveContextService;

class DashboardController extends Controller
{
    public function index()
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
        ->latest()
        ->get();

        /*
        |--------------------------------------------------------------------------
        | ALERTAS / STATUS / RESUMOS
        |--------------------------------------------------------------------------
        */

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
        
            if (!$vehicle->operational_status) {
        
                $vehicle->operational_status =
                    'operational';
            }
            
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
        
        $lowStockItems = $stockItems
            ->filter(function ($item) {
                return in_array(
                    StockService::getStatus($item),
                    ['danger', 'warning']
                );
            })
            ->values();
        
        $lowStockCount = $lowStockItems->count();

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

        $operationalVehicles =
            $vehicles
            ->where('operational_status', 'operational')
            ->count();

        $maintenanceVehicles =
            $vehicles
            ->where('operational_status', 'maintenance')
            ->count();

        $inactiveVehicles =
            $vehicles
            ->where('status', 'inactive')
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

            'maintenanceVehicles',
            'lowStockItems',
            'lowStockCount',
            'operationalUpdatePendingVehicles',
            'operationalUpdatePendingCount',
            
            'canManageOperationDrivers',
            'cannotStartOperation',
            'operationDrivers',
            'myOpenOperation',
            
            'inactiveVehicles',

        ));
    }
}
