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

        return $this->recordHistoricalReading($vehicle, 'km', $value, $user, $source, $observation, $effectiveAt, $fillingId);
    }

    /**
     * Records a past hours reading without changing the operational counter.
     */
    public function registerHistoricalHoursReading(
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
            ->where('type', 'hours')
            ->exists()) {
            return false;
        }

        return $this->recordHistoricalReading($vehicle, 'hours', $value, $user, $source, $observation, $effectiveAt, $fillingId);
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

    /** Records an observed reading that must stay out of automatic counters and consumption. */
    public function recordSuspectReading(Vehicle $vehicle, string $type, float|int $value, User $user, string $source, ?string $observation = null, CarbonInterface|string|null $readAt = null, FuelFilling|int|null $fuelFilling = null, string $reason = 'Leitura marcada como suspeita.'): bool
    {
        return $this->recordUnreliableMeterReading($vehicle, $type, (float) $value, $user, $source, $observation, $this->effectiveDate($readAt), $this->fuelFillingId($fuelFilling), Vehicle::METER_STATUS_UNRELIABLE, $reason);
    }

    /**
     * Records that the current operational counter was physically checked without
     * changing its value. This is intentionally separate from a generic touch().
     */
    public function confirmCurrentKm(Vehicle $vehicle, User $user, ?CarbonInterface $readAt = null): bool
    {
        return $this->confirmCurrentReading(
            $vehicle,
            'current_km',
            'last_km_update_at',
            'km',
            $user,
            $readAt,
        );
    }

    public function confirmCurrentHours(Vehicle $vehicle, User $user, ?CarbonInterface $readAt = null): bool
    {
        return $this->confirmCurrentReading(
            $vehicle,
            'current_hours',
            'last_hours_update_at',
            'hours',
            $user,
            $readAt,
        );
    }

    private function confirmCurrentReading(
        Vehicle $vehicle,
        string $field,
        string $updatedAtField,
        string $type,
        User $user,
        ?CarbonInterface $readAt,
    ): bool {
        if ($vehicle->{$field} === null) {
            return false;
        }

        $effectiveAt = $this->effectiveDate($readAt);
        $value = $vehicle->{$field};

        $vehicle->update([$updatedAtField => $effectiveAt]);

        VehicleUpdateLog::create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
            'division_id' => $vehicle->division_id,
            'location_id' => $vehicle->location_id,
            'type' => $type,
            'source' => 'quick_update_confirmation',
            'read_at' => $effectiveAt,
            'old_value' => $value,
            'new_value' => $value,
            'observation' => 'Leitura operacional atual confirmada na atualização rápida.',
        ]);

        return true;
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
        $meterStatus = $vehicle->{$type === 'km' ? 'km_meter_status' : 'hours_meter_status'} ?? Vehicle::METER_STATUS_NORMAL;

        if ($fillingId && VehicleUpdateLog::query()
            ->where('fuel_filling_id', $fillingId)
            ->where('type', $type)
            ->exists()) {
            return false;
        }

        if (in_array($meterStatus, [Vehicle::METER_STATUS_FAULTY, Vehicle::METER_STATUS_UNRELIABLE], true)) {
            return $this->recordUnreliableMeterReading($vehicle, $type, $numericValue, $user, $source, $observation, $effectiveAt, $fillingId, $meterStatus);
        }

        $timeline = $this->timelineContext($vehicle, $type, $effectiveAt, $numericValue);

        // Legacy vehicles can have an operational counter before their first log.
        // In that case a lower contemporaneous value remains suspicious; a dated
        // event before last_*_update_at is already classified as retroactive above.
        if (! $timeline['retroactive'] && $oldValue !== null && $numericValue < (float) $oldValue) {
            $timeline['inconsistent'] = true;
        }

        if ($timeline['inconsistent'] && ! $confirmedSuspicious) {
            throw ValidationException::withMessages([
                $errorField => $this->timelineMessage($timeline, $type),
            ]);
        }

        if (! $timeline['retroactive'] && ! $timeline['inconsistent'] && $oldValue !== null && $numericValue === (float) $oldValue) {
            return false;
        }

        if (
            ! $timeline['retroactive']
            && $oldValue !== null
            && $numericValue - (float) $oldValue > $suspiciousThreshold
            && ! $confirmedSuspicious
        ) {
            throw ValidationException::withMessages([
                $errorField => $suspiciousValueMessage,
            ]);
        }

        $updatesCounter = ! $timeline['retroactive']
            && ! $timeline['inconsistent']
            && ($oldValue === null || $numericValue > (float) $oldValue);

        if ($updatesCounter) {
            $vehicle->update([$field => $value, $updatedAtField => $effectiveAt]);
        }

        VehicleUpdateLog::create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
            'division_id' => $vehicle->division_id,
            'location_id' => $vehicle->location_id,
            'type' => $type,
            'source' => $source,
            'read_at' => $effectiveAt,
            'fuel_filling_id' => $fillingId,
            'old_value' => $timeline['previous']?->new_value ?? $oldValue,
            'new_value' => $value,
            'reading_status' => $timeline['inconsistent'] ? VehicleUpdateLog::READING_STATUS_SUSPECT : VehicleUpdateLog::READING_STATUS_VALID,
            'reading_issue' => $timeline['inconsistent'] ? $this->timelineMessage($timeline, $type) : ($timeline['retroactive'] ? 'Leitura retroativa coerente; não alterou o contador operacional atual.' : null),
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

    private function recordHistoricalReading(Vehicle $vehicle, string $type, float|int $value, User $user, string $source, ?string $observation, CarbonInterface $effectiveAt, ?int $fillingId): bool
    {
        $timeline = $this->timelineContext($vehicle, $type, $effectiveAt, (float) $value);
        VehicleUpdateLog::create([
            'vehicle_id' => $vehicle->id, 'user_id' => $user->id,
            'division_id' => $vehicle->division_id, 'location_id' => $vehicle->location_id,
            'type' => $type, 'source' => $source, 'read_at' => $effectiveAt,
            'fuel_filling_id' => $fillingId, 'old_value' => $timeline['previous']?->new_value,
            'new_value' => (float) $value,
            ...$this->readingMetadata($timeline['inconsistent'] ? VehicleUpdateLog::READING_STATUS_SUSPECT : VehicleUpdateLog::READING_STATUS_VALID, $timeline['inconsistent'] ? $this->timelineMessage($timeline, $type) : 'Leitura histórica/retroativa; contador operacional preservado.'),
            'observation' => $observation,
        ]);
        return true;
    }

    private function recordUnreliableMeterReading(Vehicle $vehicle, string $type, float $value, User $user, string $source, ?string $observation, CarbonInterface $effectiveAt, ?int $fillingId, string $meterStatus, ?string $explicitReason = null): bool
    {
        $label = $meterStatus === Vehicle::METER_STATUS_FAULTY ? 'medidor marcado como com defeito' : 'medidor marcado como não confiável';
        VehicleUpdateLog::create([
            'vehicle_id' => $vehicle->id, 'user_id' => $user->id, 'division_id' => $vehicle->division_id, 'location_id' => $vehicle->location_id,
            'type' => $type, 'source' => $source, 'read_at' => $effectiveAt, 'fuel_filling_id' => $fillingId,
            'old_value' => $vehicle->{$type === 'km' ? 'current_km' : 'current_hours'}, 'new_value' => $value,
            ...$this->readingMetadata(VehicleUpdateLog::READING_STATUS_SUSPECT, $explicitReason ?? ('Leitura observada; '.$label.'. Não altera contador nem participa de consumo.')),
            'observation' => $observation,
        ]);
        if ($type === 'km' && $fillingId && \Illuminate\Support\Facades\Schema::hasColumn('fuel_fillings', 'vehicle_km_status')) {
            FuelFilling::query()->whereKey($fillingId)->update([
                'vehicle_km_status' => FuelFilling::KM_STATUS_SUSPECT,
                'vehicle_km_issue' => 'Leitura registrada com '.$label.'.',
            ]);
        }
        return true;
    }

    /** Finds only usable readings immediately before and after the effective event time. */
    private function timelineContext(Vehicle $vehicle, string $type, CarbonInterface $at, float $value): array
    {
        $base = VehicleUpdateLog::query()->where('vehicle_id', $vehicle->id)->where('type', $type)->usableReading();
        $previous = (clone $base)->whereRaw('COALESCE(read_at, created_at) < ?', [$at])->orderByRaw('COALESCE(read_at, created_at) desc')->orderByDesc('id')->first();
        $next = (clone $base)->whereRaw('COALESCE(read_at, created_at) > ?', [$at])->orderByRaw('COALESCE(read_at, created_at) asc')->orderBy('id')->first();
        $lastAt = $vehicle->{$type === 'km' ? 'last_km_update_at' : 'last_hours_update_at'};
        $retroactive = $next !== null || ($lastAt !== null && $at->lt(Carbon::parse($lastAt)));
        $inconsistent = ($previous && $value < (float) $previous->new_value) || ($next && $value > (float) $next->new_value);
        return compact('previous', 'next', 'retroactive', 'inconsistent');
    }

    private function timelineMessage(array $timeline, string $type): string
    {
        $unit = $type === 'km' ? 'km' : 'h';
        $format = fn ($log) => number_format((float) $log->new_value, $type === 'km' ? 0 : 1, ',', '.') . " {$unit} em " . ($log->read_at ?? $log->created_at)->format('d/m/Y H:i');
        $parts = ['Esta leitura gera regressão na linha do tempo e exige confirmação explícita para ser registrada como suspeita.'];
        if ($timeline['previous']) $parts[] = 'Anterior: '.$format($timeline['previous']).'.';
        if ($timeline['next']) $parts[] = 'Posterior: '.$format($timeline['next']).'.';
        return implode(' ', $parts);
    }

    private function readingMetadata(?string $status, ?string $issue): array
    {
        if (! \Illuminate\Support\Facades\Schema::hasColumn('vehicle_update_logs', 'reading_status')) {
            return [];
        }

        return ['reading_status' => $status, 'reading_issue' => $issue];
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
