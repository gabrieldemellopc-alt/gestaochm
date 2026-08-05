<?php

namespace App\Services;

use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleUpdateLog;
use Illuminate\Validation\ValidationException;

class VehicleReadingService
{
    public const MAX_KM_JUMP = 500;

    public const MAX_HOURS_JUMP = 24;

    public function analyzeKmReading(Vehicle $vehicle, float|int $value): array
    {
        return $this->analyzeReading($vehicle->current_km, $value, self::MAX_KM_JUMP);
    }

    public function analyzeHoursReading(Vehicle $vehicle, float|int $value): array
    {
        return $this->analyzeReading($vehicle->current_hours, $value, self::MAX_HOURS_JUMP);
    }

    public function correctKm(Vehicle $vehicle, float|int $value, User $user, string $reason): bool
    {
        return $this->correctReading($vehicle, 'current_km', 'last_km_update_at', 'km', $value, $user, $reason);
    }

    public function correctHours(Vehicle $vehicle, float|int $value, User $user, string $reason): bool
    {
        return $this->correctReading($vehicle, 'current_hours', 'last_hours_update_at', 'hours', $value, $user, $reason);
    }

    public function updateKm(
        Vehicle $vehicle,
        float|int $value,
        User $user,
        string $source,
        ?string $observation = null,
        string $errorField = 'km',
        bool $confirmedSuspicious = false
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
            'O KM informado não pode ser menor que o KM atual do veículo.',
            'O KM informado parece muito acima da leitura atual. Confirme para continuar.',
            self::MAX_KM_JUMP,
            $confirmedSuspicious
        );
    }

    public function updateHours(
        Vehicle $vehicle,
        float|int $value,
        User $user,
        string $source,
        ?string $observation = null,
        string $errorField = 'hours',
        bool $confirmedSuspicious = false
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
            'O horímetro informado não pode ser menor que o horímetro atual do veículo.',
            'O horímetro informado parece muito acima da leitura atual. Confirme para continuar.',
            self::MAX_HOURS_JUMP,
            $confirmedSuspicious
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
        string $lowerValueMessage,
        string $suspiciousValueMessage,
        float|int $suspiciousThreshold,
        bool $confirmedSuspicious
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

        if (
            $oldValue !== null
            && $numericValue - (float) $oldValue > $suspiciousThreshold
            && ! $confirmedSuspicious
        ) {
            throw ValidationException::withMessages([
                $errorField => $suspiciousValueMessage,
            ]);
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

    private function analyzeReading(mixed $currentValue, float|int $value, float|int $threshold): array
    {
        $newValue = (float) $value;
        $oldValue = $currentValue !== null ? (float) $currentValue : null;
        $difference = $oldValue !== null ? $newValue - $oldValue : null;

        return [
            'current' => $oldValue,
            'new' => $newValue,
            'difference' => $difference,
            'regressive' => $oldValue !== null && $newValue < $oldValue,
            'unchanged' => $oldValue !== null && $newValue === $oldValue,
            'suspicious' => $difference !== null && $difference > $threshold,
            'threshold' => $threshold,
        ];
    }

    private function correctReading(
        Vehicle $vehicle,
        string $field,
        string $updatedAtField,
        string $type,
        float|int $value,
        User $user,
        string $reason
    ): bool {
        $oldValue = $vehicle->{$field};

        if ($oldValue !== null && (float) $oldValue === (float) $value) {
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
            'source' => 'reading_correction',
            'old_value' => $oldValue,
            'new_value' => $value,
            'observation' => 'Motivo da correção: '.$reason,
        ]);

        return true;
    }
}
