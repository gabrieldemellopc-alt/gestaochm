<?php
namespace App\Services;
use App\Models\Location;
use App\Models\Vehicle;
use Illuminate\Validation\ValidationException;
class AggregatedVehiclePolicy {
    public const MAINTENANCE_RESTRICTION_MESSAGE = 'De acordo com a configuração desta unidade, veículos agregados não podem ter manutenções abertas.';

    public function allowsFuel(Vehicle $vehicle, ?Location $location = null): bool { return $vehicle->fleet_relation !== Vehicle::FLEET_RELATION_AGGREGATED || (bool) ($location ?? $vehicle->location)?->allow_aggregated_fuel; }
    public function allowsMaintenance(Vehicle $vehicle, ?Location $location = null): bool { return $vehicle->fleet_relation !== Vehicle::FLEET_RELATION_AGGREGATED || (bool) ($location ?? $vehicle->location)?->allow_aggregated_maintenance; }
    public function maintenanceRestrictionReason(Vehicle $vehicle, ?Location $location = null): ?string { return $this->allowsMaintenance($vehicle, $location) ? null : self::MAINTENANCE_RESTRICTION_MESSAGE; }
    public function ensureFuelAllowed(Vehicle $vehicle, ?Location $location = null): void { if (! $this->allowsFuel($vehicle, $location)) throw ValidationException::withMessages(['vehicle_id' => 'Esta unidade não permite abastecimento de veículos agregados.']); }
    public function ensureMaintenanceAllowed(Vehicle $vehicle, ?Location $location = null): void { if (! $this->allowsMaintenance($vehicle, $location)) throw ValidationException::withMessages(['vehicle_id' => self::MAINTENANCE_RESTRICTION_MESSAGE]); }
}
