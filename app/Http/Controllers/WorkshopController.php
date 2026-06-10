<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Services\ActiveContextService;

class WorkshopController extends Controller
{
    public function index()
    {
        $tenantId = auth()->user()->tenant_id ?? 1;
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

        /*
        |--------------------------------------------------------------------------
        | ESTOQUE
        |--------------------------------------------------------------------------
        */

        $lowStockItems = collect();
        $lowStockCount = 0;
        $latestStockMovements = collect();

        if (Schema::hasTable('stock_items')) {
            $lowStockItems = DB::table('stock_items')
                ->where('tenant_id', $tenantId)
                ->where('location_id', $activeLocation->id)
                ->where('active', 1)
                ->whereColumn('quantity', '<=', 'minimum_quantity')
                ->orderBy('quantity')
                ->limit(5)
                ->get();

            $lowStockCount = DB::table('stock_items')
                ->where('tenant_id', $tenantId)
                ->where('location_id', $activeLocation->id)
                ->where('active', 1)
                ->whereColumn('quantity', '<=', 'minimum_quantity')
                ->count();
        }

        if (Schema::hasTable('stock_movements')) {
            $latestStockMovements = DB::table('stock_movements')
                ->leftJoin('stock_items', 'stock_items.id', '=', 'stock_movements.stock_item_id')
                ->where('stock_movements.tenant_id', $tenantId)
                ->where('stock_movements.location_id', $activeLocation->id)
                ->select(
                    'stock_movements.*',
                    'stock_items.name as item_name',
                    'stock_items.unit as item_unit'
                )
                ->latest('stock_movements.created_at')
                ->limit(5)
                ->get();
        }

        /*
        |--------------------------------------------------------------------------
        | PNEUS
        |--------------------------------------------------------------------------
        */

        $tiresAttention = collect();
        $tiresAttentionCount = 0;
        $latestTireEntries = collect();

        if (
            Schema::hasTable('tires') &&
            Schema::hasTable('tire_measurements') &&
            Schema::hasTable('tire_retreads')
        ) {
            $latestMeasurementSubquery = DB::table('tire_measurements as measurement')
                ->select('measurement.*')
                ->where('measurement.tenant_id', $tenantId)
                ->whereNotExists(function ($query) {
                    $query
                        ->select(DB::raw(1))
                        ->from('tire_measurements as newer_measurement')
                        ->whereColumn('newer_measurement.tenant_id', 'measurement.tenant_id')
                        ->whereColumn('newer_measurement.tire_id', 'measurement.tire_id')
                        ->where(function ($query) {
                            $query
                                ->whereColumn('newer_measurement.measured_at', '>', 'measurement.measured_at')
                                ->orWhere(function ($query) {
                                    $query
                                        ->whereColumn('newer_measurement.measured_at', 'measurement.measured_at')
                                        ->whereColumn('newer_measurement.id', '>', 'measurement.id');
                                });
                        });
                });

            $latestRetreadSubquery = DB::table('tire_retreads as retread')
                ->select('retread.*')
                ->where('retread.tenant_id', $tenantId)
                ->whereNotExists(function ($query) {
                    $query
                        ->select(DB::raw(1))
                        ->from('tire_retreads as newer_retread')
                        ->whereColumn('newer_retread.tenant_id', 'retread.tenant_id')
                        ->whereColumn('newer_retread.tire_id', 'retread.tire_id')
                        ->where(function ($query) {
                            $query
                                ->whereColumn('newer_retread.retreaded_at', '>', 'retread.retreaded_at')
                                ->orWhere(function ($query) {
                                    $query
                                        ->whereColumn('newer_retread.retreaded_at', 'retread.retreaded_at')
                                        ->whereColumn('newer_retread.id', '>', 'retread.id');
                                });
                        });
                });

            $currentTreadSql = "
                CASE
                    WHEN latest_measurements.id IS NOT NULL
                        AND (
                            latest_retreads.id IS NULL
                            OR latest_measurements.measured_at > latest_retreads.retreaded_at
                        )
                        THEN latest_measurements.minimum_tread
                    WHEN latest_retreads.id IS NOT NULL
                        THEN latest_retreads.new_tread_depth
                    ELSE tires.initial_tread_depth
                END
            ";

            $tiresAttentionBase = DB::table('tires')
                ->leftJoinSub($latestMeasurementSubquery, 'latest_measurements', function ($join) {
                    $join->on('latest_measurements.tire_id', '=', 'tires.id');
                })
                ->leftJoinSub($latestRetreadSubquery, 'latest_retreads', function ($join) {
                    $join->on('latest_retreads.tire_id', '=', 'tires.id');
                })
                ->where('tires.tenant_id', $tenantId)
                ->where('tires.location_id', $activeLocation->id)
                ->where(function ($query) use ($currentTreadSql) {
                    $query
                        ->whereRaw("{$currentTreadSql} <= tires.warning_tread_depth")
                        ->orWhere('tires.status', 'maintenance')
                        ->orWhere('tires.status', 'discarded');
                })
                ->select(
                    'tires.id',
                    'tires.code',
                    'tires.brand',
                    'tires.model',
                    'tires.size',
                    'tires.status',
                    'tires.initial_tread_depth',
                    'tires.warning_tread_depth',
                    'tires.critical_tread_depth',
                    'latest_measurements.vehicle_id',
                    'latest_measurements.position_code',
                    'latest_measurements.measured_at',
                    DB::raw("{$currentTreadSql} as minimum_tread"),
                    'latest_measurements.average_tread',
                    'latest_measurements.vehicle_km',

                    DB::raw("{$currentTreadSql} as current_tread_depth")
                );

            $tiresAttentionCount = (clone $tiresAttentionBase)->count();

            $tiresAttention = $tiresAttentionBase
                ->orderByRaw("{$currentTreadSql} IS NULL")
                ->orderByRaw($currentTreadSql)
                ->limit(5)
                ->get();
        }

        if (Schema::hasTable('tire_entries')) {
            $latestTireEntries = DB::table('tire_entries')
                ->where('tenant_id', $tenantId)
                ->where('location_id', $activeLocation->id)
                ->latest('created_at')
                ->limit(5)
                ->get();
        }

        /*
        |--------------------------------------------------------------------------
        | PROCEDIMENTOS
        |--------------------------------------------------------------------------
        */

        $proceduresPreview = collect();
        $proceduresCount = 0;

        if (Schema::hasTable('procedures')) {
            $proceduresPreview = DB::table('procedures')
                ->where('tenant_id', $tenantId)
                ->where('location_id', $activeLocation->id)
                ->latest('created_at')
                ->limit(5)
                ->get();

            $proceduresCount = DB::table('procedures')
                ->where('tenant_id', $tenantId)
                ->where('location_id', $activeLocation->id)
                ->count();
        }

        /*
        |--------------------------------------------------------------------------
        | VEÍCULOS EM MANUTENÇÃO / ATENÇÃO
        |--------------------------------------------------------------------------
        |
        | Aqui deixei duas camadas:
        | 1. Se vehicles.status existir, usa ele.
        | 2. Se não existir ou não tiver registros, usa últimas manutenções.
        |
        */

        $vehiclesInMaintenance = collect();
        $maintenanceVehiclesCount = 0;

        if (Schema::hasTable('vehicles')) {
            if (Schema::hasColumn('vehicles', 'status')) {
                $vehiclesInMaintenance = DB::table('vehicles')
                    ->where('vehicles.tenant_id', $tenantId)
                    ->where('vehicles.location_id', $activeLocation->id)
                    ->where('vehicles.division_id', $activeDivisionId)
                    ->whereIn('status', ['maintenance', 'inactive', 'stopped'])
                    ->latest('updated_at')
                    ->limit(6)
                    ->get();

                $maintenanceVehiclesCount = DB::table('vehicles')
                    ->where('vehicles.tenant_id', $tenantId)
                    ->where('vehicles.location_id', $activeLocation->id)
                    ->where('vehicles.division_id', $activeDivisionId)
                    ->whereIn('status', ['maintenance', 'inactive', 'stopped'])
                    ->count();
            }

            if (
                $vehiclesInMaintenance->isEmpty() &&
                Schema::hasTable('maintenance_records') &&
                Schema::hasColumn('maintenance_records', 'vehicle_id')
            ) {
                $vehiclesInMaintenance = DB::table('maintenance_records')
                    ->leftJoin('vehicles', 'vehicles.id', '=', 'maintenance_records.vehicle_id')
                    ->where('vehicles.tenant_id', $tenantId)
                    ->where('vehicles.location_id', $activeLocation->id)
                    ->where('vehicles.division_id', $activeDivisionId)
                    ->select(
                        'vehicles.id',
                        'vehicles.name',
                        'vehicles.plate',
                        'vehicles.type',
                        DB::raw("'maintenance' as status"),
                        'maintenance_records.created_at as maintenance_created_at'
                    )
                    ->latest('maintenance_records.created_at')
                    ->limit(6)
                    ->get();

                $maintenanceVehiclesCount = DB::table('maintenance_records')
                    ->leftJoin('vehicles', 'vehicles.id', '=', 'maintenance_records.vehicle_id')
                    ->where('vehicles.tenant_id', $tenantId)
                    ->where('vehicles.location_id', $activeLocation->id)
                    ->where('vehicles.division_id', $activeDivisionId)
                    ->distinct('maintenance_records.vehicle_id')
                    ->count('maintenance_records.vehicle_id');
            }
        }

        return view('workshop.index', compact(
            'lowStockItems',
            'lowStockCount',
            'latestStockMovements',
            'tiresAttention',
            'tiresAttentionCount',
            'latestTireEntries',
            'proceduresPreview',
            'proceduresCount',
            'vehiclesInMaintenance',
            'maintenanceVehiclesCount'
        ));
    }
}
