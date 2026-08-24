<?php

namespace App\Services;

use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleUpdateLog;
use App\Models\FuelFilling;
use Carbon\Carbon;
use Carbon\CarbonInterface;
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
        bool $confirmedSuspicious = false,
        CarbonInterface|string|null $readAt = null,
        FuelFilling|int|null $fuelFilling = null,
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
            $confirmedSuspicious,
            $readAt,
            $fuelFilling,
        );
    }

    public function registerInitialKm(
        Vehicle $vehicle,
        float|int $value,
        User $user,
        CarbonInterface|string|null $readAt = null,
        ?string $observation = null,
    ): void {
        if (VehicleUpdateLog::query()->where('vehicle_id', $vehicle->id)->where('type', 'km')->exists()) {
            throw ValidationException::withMessages([
                'vehicle' => 'O veículo já possui histórico de KM e não aceita leitura inicial.',
            ]);
        }

        $effectiveAt = $this->effectiveDate($readAt);
        $vehicle->update([
            'current_km' => $value,
            'last_km_update_at' => $effectiveAt,
        ]);

        VehicleUpdateLog::create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
            'division_id' => $vehicle->division_id,
            'location_id' => $vehicle->location_id,
            'type' => 'km',
            'source' => 'initial_registration',
            'read_at' => $effectiveAt,
            'old_value' => null,
            'new_value' => $value,
            'observation' => $observation ?? 'Cadastro inicial.',
        ]);
    }

    public function registerInitialHours(
        Vehicle $vehicle,
        float|int $value,
        User $user,
        CarbonInterface|string|null $readAt = null,
        ?string $observation = null,
    ): void {
        if (VehicleUpdateLog::query()->where('vehicle_id', $vehicle->id)->where('type', 'hours')->exists()) {
            throw ValidationException::withMessages([
                'vehicle' => 'O veículo já possui histórico de horas e não aceita leitura inicial.',
            ]);
        }

        $effectiveAt = $this->effectiveDate($readAt);
        $vehicle->update([
            'current_hours' => $value,
            'last_hours_update_at' => $effectiveAt,
        ]);

        VehicleUpdateLog::create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
            'division_id' => $vehicle->division_id,
            'location_id' => $vehicle->location_id,
            'type' => 'hours',
            'source' => 'initial_registration',
            'read_at' => $effectiveAt,
            'old_value' => null,
            'new_value' => $value,
            'observation' => $observation ?? 'Cadastro inicial.',
        ]);
    }

    /**
     * Records a past KM reading without regressing the vehicle's current operational counter.
     */
    public function registerHistoricalKmReading(
        Vehicle $vehicle,
        float|int $value,
        User $user,
        CarbonInterface|string $readAt,
        string $source,
        ?string $observation = null,
        FuelFilling|int|null $fuelFilling = null,
    ): bool {
        $effectiveAt = $this->effectiveDate($readAt);
        $fillingId = $this->fuelFillingId($fuelFilling);

        if ($fillingId && VehicleUpdateLog::query()
            ->where('fuel_filling_id', $fillingId)
            ->where('type', 'km')
            ->exists()) {
            return false;
        }

        $numericValue = (float) $value;
        $previousValue = VehicleUpdateLog::query()
            ->where('vehicle_id', $vehicle->id)
            ->where('type', 'km')
            ->whereRaw('COALESCE(read_at, created_at) < ?', [$effectiveAt])
            ->orderByRaw('COALESCE(read_at, created_at) desc')
            ->value('new_value');

        VehicleUpdateLog::create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
            'division_id' => $vehicle->division_id,
            'location_id' => $vehicle->location_id,
            'type' => 'km',
            'source' => $source,
            'read_at' => $effectiveAt,
            'fuel_filling_id' => $fillingId,
            'old_value' => $previousValue,
            'new_value' => $numericValue,
            'observation' => $observation,
        ]);

        // A historical entry may become current only when it is newer than the current reading's effective date.
        $canBecomeCurrent = $vehicle->current_km === null
            || ($vehicle->last_km_update_at !== null
                && $effectiveAt->gte(Carbon::parse($vehicle->last_km_update_at))
                && $numericValue >= (float) $vehicle->current_km);

        if ($canBecomeCurrent) {
            $vehicle->update([
                'current_km' => $numericValue,
                'last_km_update_at' => $effectiveAt,
            ]);
        }

        return true;
    }

    public function updateHours(
        Vehicle $vehicle,
        float|int $value,
        User $user,
        string $source,
        ?string $observation = null,
        string $errorField = 'hours',
        bool $confirmedSuspicious = false,
        CarbonInterface|string|null $readAt = null,
        FuelFilling|int|null $fuelFilling = null,
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
            $confirmedSuspicious,
            $readAt,
            $fuelFilling,
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
        bool $confirmedSuspicious,
        CarbonInterface|string|null $readAt = null,
        FuelFilling|int|null $fuelFilling = null,
    ): bool {
        $oldValue = $vehicle->{$field};
        $numericValue = (float) $value;
        $effectiveAt = $this->effectiveDate($readAt);
        $fillingId = $this->fuelFillingId($fuelFilling);

        if ($fillingId && VehicleUpdateLog::query()
            ->where('fuel_filling_id', $fillingId)
            ->where('type', $type)
            ->exists()) {
            return false;
        }

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
            $updatedAtField => $effectiveAt,
        ]);

        VehicleUpdateLog::create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
            'division_id' => $vehicle->division_id,
            'location_id' => $vehicle->location_id,
            'type' => $type,
            'source' => $source,
            'read_at' => $effectiveAt,
            'fuel_filling_id' => $fillingId,
            'old_value' => $oldValue,
            'new_value' => $value,
            'observation' => $observation,
        ]);

        return true;
    }

    private function effectiveDate(CarbonInterface|string|null $readAt): CarbonInterface
    {
        return $readAt instanceof CarbonInterface ? $readAt : Carbon::parse($readAt ?? now());
    }

    private function fuelFillingId(FuelFilling|int|null $fuelFilling): ?int
    {
        return $fuelFilling instanceof FuelFilling ? $fuelFilling->id : $fuelFilling;
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
        $effectiveAt = now();

        if ($oldValue !== null && (float) $oldValue === (float) $value) {
            return false;
        }

        $vehicle->update([
            $field => $value,
            $updatedAtField => $effectiveAt,
        ]);

        VehicleUpdateLog::create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
            'division_id' => $vehicle->division_id,
            'location_id' => $vehicle->location_id,
            'type' => $type,
            'source' => 'reading_correction',
            'read_at' => $effectiveAt,
            'old_value' => $oldValue,
            'new_value' => $value,
            'observation' => 'Motivo da correção: '.$reason,
        ]);

        return true;
    }
}
