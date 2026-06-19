<?php

namespace App\Services;

use App\Models\FuelFilling;
use App\Models\FuelMovement;
use App\Models\FuelReceipt;
use App\Models\FuelTank;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class FuelService
{
    public function __construct(
        private readonly ActiveContextService $activeContext,
        private readonly AuditLogService $auditLog,
    ) {
    }

    public function receiveFuel(array $data): FuelReceipt
    {
        $context = $this->resolveContext();

        $validated = Validator::make($data, [
            'fuel_tank_id' => ['required', 'integer'],
            'fuel_product_id' => ['nullable', 'integer'],
            'received_at' => ['required', 'date'],
            'quantity_liters' => ['required', 'numeric', 'gt:0'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'total_cost' => ['nullable', 'numeric', 'min:0'],
            'supplier_name' => ['nullable', 'string', 'max:255'],
            'invoice_number' => ['nullable', 'string', 'max:255'],
            'responsible_user_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string'],
        ])->validate();

        return DB::transaction(function () use ($context, $validated) {
            $tank = $this->lockTankForContext((int) $validated['fuel_tank_id'], $context);
            $this->ensureProductMatchesTank($tank, $validated['fuel_product_id'] ?? null);

            $quantity = $this->decimal($validated['quantity_liters'], 3);
            $unitCost = $this->nullableDecimal($validated['unit_cost'] ?? null, 4);
            $totalCost = $this->resolveTotalCost($quantity, $unitCost, $validated['total_cost'] ?? null);
            $balanceBefore = $this->decimal($tank->current_balance_liters, 3);
            $balanceAfter = $this->decimal($balanceBefore + $quantity, 3);
            $responsibleUserId = $validated['responsible_user_id'] ?? $context['user']->id;

            $receipt = FuelReceipt::query()->create([
                'tenant_id' => $context['tenant_id'],
                'division_id' => $context['division_id'],
                'location_id' => $context['location_id'],
                'fuel_tank_id' => $tank->id,
                'fuel_product_id' => $tank->fuel_product_id,
                'received_at' => Carbon::parse($validated['received_at']),
                'quantity_liters' => $quantity,
                'unit_cost' => $unitCost,
                'total_cost' => $totalCost,
                'supplier_name' => $validated['supplier_name'] ?? null,
                'invoice_number' => $validated['invoice_number'] ?? null,
                'responsible_user_id' => $responsibleUserId,
                'notes' => $validated['notes'] ?? null,
            ]);

            $tank->forceFill([
                'current_balance_liters' => $balanceAfter,
            ])->save();

            $movement = $this->createMovement(
                $context,
                $tank,
                FuelMovement::TYPE_RECEIPT,
                $quantity,
                $balanceBefore,
                $balanceAfter,
                $receipt,
                $responsibleUserId,
                'Recebimento de combustível'
            );

            $this->auditLog->created($receipt, [
                'tenant_id' => $context['tenant_id'],
                'division_id' => $context['division_id'],
                'location_id' => $context['location_id'],
                'module' => 'fuel',
                'summary' => "Recebimento de {$quantity} litros registrado no tanque {$tank->name}.",
                'after_data' => $receipt->toArray(),
                'metadata' => [
                    'fuel_movement_id' => $movement->id,
                    'fuel_tank_id' => $tank->id,
                    'balance_before' => $balanceBefore,
                    'balance_after' => $balanceAfter,
                ],
            ]);

            return $receipt;
        });
    }

    public function registerFilling(array $data): FuelFilling
    {
        $context = $this->resolveContext();

        $validated = Validator::make($data, [
            'fuel_tank_id' => ['required', 'integer'],
            'fuel_product_id' => ['nullable', 'integer'],
            'vehicle_id' => ['required', 'integer'],
            'driver_id' => ['nullable', 'integer'],
            'filled_at' => ['required', 'date'],
            'vehicle_km' => ['nullable', 'numeric', 'min:0'],
            'vehicle_hours' => ['nullable', 'numeric', 'min:0'],
            'quantity_liters' => ['required', 'numeric', 'gt:0'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'total_cost' => ['nullable', 'numeric', 'min:0'],
            'responsible_user_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string'],
        ])->validate();

        return DB::transaction(function () use ($context, $validated) {
            $vehicle = $this->vehicleForContext((int) $validated['vehicle_id'], $context);
            $this->validateVehicleCounters($vehicle, $validated);

            $tank = $this->lockTankForContext((int) $validated['fuel_tank_id'], $context);
            $this->ensureProductMatchesTank($tank, $validated['fuel_product_id'] ?? null);

            $quantity = $this->decimal($validated['quantity_liters'], 3);
            $unitCost = $this->nullableDecimal($validated['unit_cost'] ?? null, 4);
            $totalCost = $this->resolveTotalCost($quantity, $unitCost, $validated['total_cost'] ?? null);
            $balanceBefore = $this->decimal($tank->current_balance_liters, 3);

            if ($quantity > $balanceBefore) {
                throw ValidationException::withMessages([
                    'quantity_liters' => 'A quantidade abastecida não pode ser maior que o saldo atual do tanque.',
                ]);
            }

            $balanceAfter = $this->decimal($balanceBefore - $quantity, 3);
            $responsibleUserId = $validated['responsible_user_id'] ?? $context['user']->id;

            $filling = FuelFilling::query()->create([
                'tenant_id' => $context['tenant_id'],
                'division_id' => $context['division_id'],
                'location_id' => $context['location_id'],
                'fuel_tank_id' => $tank->id,
                'fuel_product_id' => $tank->fuel_product_id,
                'vehicle_id' => $vehicle->id,
                'driver_id' => $validated['driver_id'] ?? null,
                'filled_at' => Carbon::parse($validated['filled_at']),
                'vehicle_km' => $validated['vehicle_km'] ?? null,
                'vehicle_hours' => $validated['vehicle_hours'] ?? null,
                'quantity_liters' => $quantity,
                'unit_cost' => $unitCost,
                'total_cost' => $totalCost,
                'responsible_user_id' => $responsibleUserId,
                'notes' => $validated['notes'] ?? null,
            ]);

            $tank->forceFill([
                'current_balance_liters' => $balanceAfter,
            ])->save();

            $movement = $this->createMovement(
                $context,
                $tank,
                FuelMovement::TYPE_FILLING,
                $quantity,
                $balanceBefore,
                $balanceAfter,
                $filling,
                $responsibleUserId,
                'Abastecimento de veículo'
            );

            $this->auditLog->created($filling, [
                'tenant_id' => $context['tenant_id'],
                'division_id' => $context['division_id'],
                'location_id' => $context['location_id'],
                'module' => 'fuel',
                'summary' => "Abastecimento de {$quantity} litros registrado para o veículo {$vehicle->name}.",
                'after_data' => $filling->toArray(),
                'metadata' => [
                    'fuel_movement_id' => $movement->id,
                    'fuel_tank_id' => $tank->id,
                    'vehicle_id' => $vehicle->id,
                    'balance_before' => $balanceBefore,
                    'balance_after' => $balanceAfter,
                ],
            ]);

            return $filling;
        });
    }

    private function resolveContext(): array
    {
        /** @var User|null $user */
        $user = auth()->user();

        if (! $user) {
            throw ValidationException::withMessages([
                'user' => 'Usuário autenticado não encontrado.',
            ]);
        }

        $division = $this->activeContext->activeDivision($user);
        $location = $this->activeContext->activeLocation($user);

        if (! $division || ! $location) {
            throw ValidationException::withMessages([
                'location_id' => 'Selecione uma divisão e unidade ativa antes de registrar abastecimentos.',
            ]);
        }

        return [
            'user' => $user,
            'tenant_id' => $user->tenant_id,
            'division_id' => $division->id,
            'location_id' => $location->id,
        ];
    }

    private function lockTankForContext(int $tankId, array $context): FuelTank
    {
        $tank = FuelTank::query()
            ->where('tenant_id', $context['tenant_id'])
            ->where('division_id', $context['division_id'])
            ->where('location_id', $context['location_id'])
            ->where('active', true)
            ->lockForUpdate()
            ->find($tankId);

        if (! $tank) {
            throw ValidationException::withMessages([
                'fuel_tank_id' => 'Tanque não encontrado para a unidade ativa.',
            ]);
        }

        return $tank;
    }

    private function vehicleForContext(int $vehicleId, array $context): Vehicle
    {
        $vehicle = Vehicle::query()
            ->where('tenant_id', $context['tenant_id'])
            ->where('division_id', $context['division_id'])
            ->where('location_id', $context['location_id'])
            ->find($vehicleId);

        if (! $vehicle) {
            throw ValidationException::withMessages([
                'vehicle_id' => 'Veículo não encontrado para a unidade ativa.',
            ]);
        }

        return $vehicle;
    }

    private function validateVehicleCounters(Vehicle $vehicle, array $validated): void
    {
        if (
            array_key_exists('vehicle_km', $validated)
            && $validated['vehicle_km'] !== null
            && $vehicle->current_km !== null
            && (float) $validated['vehicle_km'] < (float) $vehicle->current_km
        ) {
            throw ValidationException::withMessages([
                'vehicle_km' => 'O KM informado não pode ser menor que o KM atual do veículo.',
            ]);
        }

        if (
            array_key_exists('vehicle_hours', $validated)
            && $validated['vehicle_hours'] !== null
            && $vehicle->current_hours !== null
            && (float) $validated['vehicle_hours'] < (float) $vehicle->current_hours
        ) {
            throw ValidationException::withMessages([
                'vehicle_hours' => 'As horas informadas não podem ser menores que as horas atuais do veículo.',
            ]);
        }
    }

    private function ensureProductMatchesTank(FuelTank $tank, ?int $fuelProductId): void
    {
        if ($fuelProductId !== null && (int) $fuelProductId !== (int) $tank->fuel_product_id) {
            throw ValidationException::withMessages([
                'fuel_product_id' => 'O produto informado não corresponde ao produto do tanque.',
            ]);
        }
    }

    private function createMovement(
        array $context,
        FuelTank $tank,
        string $movementType,
        float $quantity,
        float $balanceBefore,
        float $balanceAfter,
        FuelReceipt|FuelFilling $source,
        int $responsibleUserId,
        string $notes,
    ): FuelMovement {
        return FuelMovement::query()->create([
            'tenant_id' => $context['tenant_id'],
            'division_id' => $context['division_id'],
            'location_id' => $context['location_id'],
            'fuel_tank_id' => $tank->id,
            'fuel_product_id' => $tank->fuel_product_id,
            'movement_type' => $movementType,
            'quantity_liters' => $quantity,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'source_type' => $source::class,
            'source_id' => $source->id,
            'responsible_user_id' => $responsibleUserId,
            'notes' => $notes,
        ]);
    }

    private function resolveTotalCost(float $quantity, ?float $unitCost, mixed $totalCost): ?float
    {
        if ($totalCost !== null && $totalCost !== '') {
            return $this->decimal($totalCost, 2);
        }

        if ($unitCost === null) {
            return null;
        }

        return $this->decimal($quantity * $unitCost, 2);
    }

    private function nullableDecimal(mixed $value, int $precision): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->decimal($value, $precision);
    }

    private function decimal(mixed $value, int $precision): float
    {
        return round((float) $value, $precision);
    }
}
