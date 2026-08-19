<?php

namespace App\Services;

use App\Models\FuelFilling;
use App\Models\Vehicle;
use App\Models\VehicleUpdateLog;

class VehicleReadingReconciliationService
{
    public function latestValid(Vehicle $vehicle): ?array
    {
        $events = collect();
        VehicleUpdateLog::query()->where('vehicle_id', $vehicle->id)->where('type', 'km')->usableReading()->get()
            ->each(fn ($log) => $events->push(['date' => $log->read_at ?? $log->created_at, 'km' => (float) $log->new_value, 'source' => $log->source ?? 'vehicle_update_log', 'log_id' => $log->id, 'fuel_filling_id' => $log->fuel_filling_id]));
        FuelFilling::query()->where('vehicle_id', $vehicle->id)->whereNotNull('vehicle_km')->whereNull('cancelled_at')
            ->where(fn ($q) => $q->whereNull('vehicle_km_status')->orWhere('vehicle_km_status', FuelFilling::KM_STATUS_VALID))->get()
            ->each(fn ($filling) => $events->push(['date' => $filling->filled_at, 'km' => (float) $filling->vehicle_km, 'source' => 'fuel_filling', 'log_id' => null, 'fuel_filling_id' => $filling->id]));
        return $events->sortBy(fn ($e) => $e['date']->format('Y-m-d H:i:s').str_pad((string) ($e['log_id'] ?? $e['fuel_filling_id']), 12, '0', STR_PAD_LEFT))->last();
    }
}
