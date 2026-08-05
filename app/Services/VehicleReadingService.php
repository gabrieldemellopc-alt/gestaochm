<?php

namespace App\Services;

use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleUpdateLog;
use Illuminate\Validation\ValidationException;

class VehicleReadingService
{
    public function updateKm(
        Vehicle $vehicle,
        float|int $value,
        User $user,
        string $source,
        ?string $observation = null,
        string $errorField = 'km'
    ): bool {
        return $this->updateReading(
            $vehicle,
            'current_km',
            'last_km_update_at',
            'km',
            $value,
            $user,
            $source,
            $observation,
            $errorField,
            'O KM informado não pode ser menor que o KM atual do veículo.'
        );
    }

    public function updateHours(
        Vehicle $vehicle,
        float|int $value,
        User $user,
        string $source,
        ?string $observation = null,
        string $errorField = 'hours'
    ): bool {
        return $this->updateReading(
            $vehicle,
            'current_hours',
            'last_hours_update_at',
            'hours',
            $value,
            $user,
            $source,
            $observation,
            $errorField,
            'O horímetro informado não pode ser menor que o horímetro atual do veículo.'
        );
    }

    private function updateReading(
        Vehicle $vehicle,
        string $field,
        string $updatedAtField,
        string $type,
        float|int $value,
        User $user,
        string $source,
        ?string $observation,
        string $errorField,
        string $lowerValueMessage
    ): bool {
        $oldValue = $vehicle->{$field};
        $numericValue = (float) $value;

        if ($oldValue !== null && $numericValue < (float) $oldValue) {
            throw ValidationException::withMessages([
                $errorField => $lowerValueMessage,
            ]);
        }

        if ($oldValue !== null && $numericValue === (float) $oldValue) {
            return false;
        }

        $vehicle->update([
            $field => $value,
            $updatedAtField => now(),
        ]);

        VehicleUpdateLog::create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
            'division_id' => $vehicle->division_id,
            'location_id' => $vehicle->location_id,
            'type' => $type,
            'source' => $source,
            'old_value' => $oldValue,
            'new_value' => $value,
            'observation' => $observation,
        ]);

        return true;
    }
}
