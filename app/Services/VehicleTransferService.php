<?php

namespace App\Services;

use App\Models\Location;
use App\Models\MaintenanceRecord;
use App\Models\SystemAuditLog;
use App\Models\Tire;
use App\Models\Vehicle;
use App\Models\VehicleAllocation;
use App\Models\VehicleOperation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VehicleTransferService
{
    public function transfer(Vehicle $vehicle, Location $destination, string $reason, User $user): Vehicle
    {
        return DB::transaction(function () use ($vehicle, $destination, $reason, $user) {
            $vehicle = Vehicle::query()->lockForUpdate()->findOrFail($vehicle->id);
            $origin = Location::query()->whereKey($vehicle->location_id)->firstOrFail();

            if (VehicleOperation::query()->where('vehicle_id', $vehicle->id)->where('status', 'open')->exists()) {
                throw ValidationException::withMessages(['transfer' => 'Não é possível transferir o veículo enquanto houver uma operação em andamento.']);
            }
            if (MaintenanceRecord::query()->where('vehicle_id', $vehicle->id)->whereNull('cancelled_at')->where('workflow_status', 'open')->exists()) {
                throw ValidationException::withMessages(['transfer' => 'Não é possível transferir o veículo enquanto houver uma ordem de manutenção aberta.']);
            }

            $current = VehicleAllocation::query()->where('vehicle_id', $vehicle->id)->where('is_current', true)->lockForUpdate()->get();
            if ($current->count() > 1) {
                throw ValidationException::withMessages(['transfer' => 'Transferência não realizada: foram encontradas múltiplas alocações atuais para este veículo.']);
            }
            if ($current->isEmpty()) {
                VehicleAllocation::create(['vehicle_id' => $vehicle->id, 'division_id' => $vehicle->division_id, 'location_id' => $vehicle->location_id, 'started_at' => now()->toDateString(), 'ended_at' => now()->toDateString(), 'is_current' => false]);
            } else {
                $current->first()->update(['ended_at' => now()->toDateString(), 'is_current' => false]);
            }

            VehicleAllocation::create(['vehicle_id' => $vehicle->id, 'division_id' => $destination->division_id, 'location_id' => $destination->id, 'started_at' => now()->toDateString(), 'is_current' => true]);
            $vehicle->update(['division_id' => $destination->division_id, 'location_id' => $destination->id]);

            $tireIds = Tire::query()->where('tenant_id', $vehicle->tenant_id)->whereHas('activeInstallation', fn ($query) => $query->where('vehicle_id', $vehicle->id))->pluck('id');
            Tire::query()->whereIn('id', $tireIds)->update(['location_id' => $destination->id]);

            SystemAuditLog::create([
                'tenant_id' => $vehicle->tenant_id, 'division_id' => $destination->division_id, 'location_id' => $destination->id,
                'user_id' => $user->id, 'module' => 'fleet', 'action' => 'vehicle_transferred',
                'auditable_type' => Vehicle::class, 'auditable_id' => $vehicle->id,
                'summary' => 'Veículo transferido de '.$origin->name.' para '.$destination->name.'.', 'reason' => $reason,
                'before_data' => ['division_id' => $origin->division_id, 'location_id' => $origin->id, 'location_name' => $origin->name],
                'after_data' => ['division_id' => $destination->division_id, 'location_id' => $destination->id, 'location_name' => $destination->name],
                'metadata' => ['vehicle_id' => $vehicle->id, 'transferred_tire_ids' => $tireIds->all()],
            ]);

            return $vehicle->fresh();
        });
    }
}
