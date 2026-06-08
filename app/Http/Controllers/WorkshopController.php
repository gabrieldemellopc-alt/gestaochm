<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WorkshopController extends Controller
{
    public function index()
    {
        $tenantId = auth()->user()->tenant_id ?? 1;
        $activeDivisionId = session('active_division_id');

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
                ->where('active', 1)
                ->whereColumn('quantity', '<=', 'minimum_quantity')
                ->orderBy('quantity')
                ->limit(5)
                ->get();

            $lowStockCount = DB::table('stock_items')
                ->where('tenant_id', $tenantId)
                ->where('active', 1)
                ->whereColumn('quantity', '<=', 'minimum_quantity')
                ->count();
        }

        if (Schema::hasTable('stock_movements')) {
            $latestStockMovements = DB::table('stock_movements')
                ->leftJoin('stock_items', 'stock_items.id', '=', 'stock_movements.stock_item_id')
                ->where('stock_movements.tenant_id', $tenantId)
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
            Schema::hasTable('tire_measurements')
        ) {
            $latestMeasurementSubquery = DB::table('tire_measurements')
                ->select('tire_id', DB::raw('MAX(id) as latest_measurement_id'))
                ->where('tenant_id', $tenantId)
                ->groupBy('tire_id');

            $tiresAttentionBase = DB::table('tires')
                ->leftJoinSub($latestMeasurementSubquery, 'latest_measurements', function ($join) {
                    $join->on('latest_measurements.tire_id', '=', 'tires.id');
                })
                ->leftJoin('tire_measurements', 'tire_measurements.id', '=', 'latest_measurements.latest_measurement_id')
                ->where('tires.tenant_id', $tenantId)
                ->where(function ($query) {
                    $query
                        ->whereColumn('tire_measurements.minimum_tread', '<=', 'tires.warning_tread_depth')
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
                    'tire_measurements.vehicle_id',
                    'tire_measurements.position_code',
                    'tire_measurements.measured_at',
                    'tire_measurements.minimum_tread',
                    'tire_measurements.average_tread',
                    'tire_measurements.vehicle_km'
                );

            $tiresAttentionCount = (clone $tiresAttentionBase)->count();

            $tiresAttention = $tiresAttentionBase
                ->orderByRaw('tire_measurements.minimum_tread IS NULL')
                ->orderBy('tire_measurements.minimum_tread')
                ->limit(5)
                ->get();
        }

        if (Schema::hasTable('tire_entries')) {
            $latestTireEntries = DB::table('tire_entries')
                ->where('tenant_id', $tenantId)
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
                ->latest('created_at')
                ->limit(5)
                ->get();

            $proceduresCount = DB::table('procedures')
                ->where('tenant_id', $tenantId)
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
                    ->when($activeDivisionId, function ($query) use ($activeDivisionId) {
                        $query->where('division_id', $activeDivisionId);
                    })
                    ->whereIn('status', ['maintenance', 'inactive', 'stopped'])
                    ->latest('updated_at')
                    ->limit(6)
                    ->get();

                $maintenanceVehiclesCount = DB::table('vehicles')
                    ->when($activeDivisionId, function ($query) use ($activeDivisionId) {
                        $query->where('division_id', $activeDivisionId);
                    })
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
                    ->when($activeDivisionId, function ($query) use ($activeDivisionId) {
                        $query->where('vehicles.division_id', $activeDivisionId);
                    })
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
                    ->when($activeDivisionId, function ($query) use ($activeDivisionId) {
                        $query->where('vehicles.division_id', $activeDivisionId);
                    })
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