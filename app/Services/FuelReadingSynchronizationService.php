<?php

namespace App\Services;

use App\Models\FuelFilling;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Collection;

class FuelReadingSynchronizationService
{
    public function eligibleFillings(array $scope): Collection
    {
        return FuelFilling::query()
            ->whereNotNull('vehicle_id')
            ->whereNotNull('vehicle_km')
            ->whereNull('cancelled_at')
            ->where(fn ($q) => $q->whereNull('vehicle_km_status')->orWhere('vehicle_km_status', FuelFilling::KM_STATUS_VALID))
            ->where(fn ($q) => $q->whereNull('vehicle_km_status')->orWhere('vehicle_km_status', FuelFilling::KM_STATUS_VALID))
            ->where(fn ($q) => $q->whereNull('vehicle_km_status')->orWhere('vehicle_km_status', FuelFilling::KM_STATUS_VALID))
            ->when($scope['tenant_id'] ?? null, fn ($q, $id) => $q->where('tenant_id', $id))
            ->when($scope['division_id'] ?? null, fn ($q, $id) => $q->where('division_id', $id))
            ->when($scope['location_id'] ?? null, fn ($q, $id) => $q->where('location_id', $id))
            ->when($scope['vehicle_id'] ?? null, fn ($q, $id) => $q->where('vehicle_id', $id))
            ->whereDoesntHave('vehicleReadingLogs', fn ($q) => $q->where('type', 'km'))
            ->with('vehicle')
            ->orderBy('vehicle_id')
            ->orderBy('filled_at')
            ->orderBy('id')
            ->get();
    }

    public function anomalies(Collection $fillings): Collection
    {
        return $fillings->groupBy('vehicle_id')->flatMap(function (Collection $vehicleFillings) {
            $issues = collect();
            $previous = null;
            $ordered = $vehicleFillings->sortBy([['filled_at', 'asc'], ['id', 'asc']])->values();
            foreach ($ordered as $index => $filling) {
                $km = (float) $filling->vehicle_km;
                if ($km === 0.0) $issues->push($this->issue('km_zero', $filling, 'KM igual a zero.'));
                if ($previous) {
                    $previousKm = (float) $previous->vehicle_km;
                    if ($km < $previousKm) $issues->push($this->issue('regression', $filling, "Regressão: {$previousKm} para {$km} km."));
                    if ($km - $previousKm > VehicleReadingService::MAX_KM_JUMP) $issues->push($this->issue('large_jump', $filling, "Salto de ".($km - $previousKm).' km.'));
                    if ($filling->filled_at->isSameDay($previous->filled_at) && $km !== $previousKm) $issues->push($this->issue('same_date_multiple_readings', $filling, 'Múltiplas leituras na mesma data; horário histórico pode ser artificial.'));
                }
                $next = $ordered->get($index + 1);
                if ($previous && $next && $km > (float) $previous->vehicle_km && $km > (float) $next->vehicle_km && ($km - (float) $previous->vehicle_km > VehicleReadingService::MAX_KM_JUMP || $km - (float) $next->vehicle_km > VehicleReadingService::MAX_KM_JUMP)) $issues->push($this->issue('spike_and_return', $filling, 'Salto extraordinário seguido de retorno à faixa anterior.'));
                $previous = $filling;
            }
            return $issues;
        })->values();
    }

    public function syncVehicle(Vehicle $vehicle, Collection $fillings, User $user): int
    {
        $service = app(VehicleReadingService::class);
        $created = 0;
        foreach ($fillings->sortBy([['filled_at', 'asc'], ['id', 'asc']]) as $filling) {
            if ($service->registerHistoricalKmReading(
                $vehicle,
                $filling->vehicle_km,
                $user,
                $filling->filled_at,
                'fuel_filling_import',
                "Leitura histórica reconstruída a partir do abastecimento #{$filling->id}.",
                $filling,
            )) $created++;
        }
        return $created;
    }

    private function issue(string $type, FuelFilling $filling, string $message): array
    {
        return ['type' => $type, 'vehicle_id' => $filling->vehicle_id, 'filling_id' => $filling->id, 'filled_at' => $filling->filled_at, 'vehicle_km' => (float) $filling->vehicle_km, 'message' => $message];
    }
}
