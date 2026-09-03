<?php

namespace App\Services;

use App\Models\FuelFilling;
use App\Models\FuelMovement;
use App\Models\FuelProduct;
use App\Models\FuelReceipt;
use App\Models\FuelTank;
use App\Models\User;
use App\Models\UserDivisionAccess;
use App\Models\Vehicle;
use App\Models\VehicleUpdateLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class FuelService
{
    public function __construct(
        private readonly ActiveContextService $activeContext,
        private readonly AuditLogService $auditLog,
        private readonly VehicleReadingService $vehicleReadingService,
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
            'supplier_id' => ['nullable', 'integer'],
            'invoice_number' => ['nullable', 'string', 'max:255'],
            'responsible_user_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string'],
        ])->validate();

        $validated=array_merge($validated,app(SupplierSnapshotService::class)->resolve($context['tenant_id'],$validated['supplier_id']??null,$validated['supplier_name']??null));
        return DB::transaction(function () use ($context, $validated) {
            $tank = $this->lockTankForContext((int) $validated['fuel_tank_id'], $context);
            $this->ensureProductMatchesTank($tank, $validated['fuel_product_id'] ?? null);

            $quantity = $this->decimal($validated['quantity_liters'], 3);
            $totalCost = $this->nullableDecimal($validated['total_cost'] ?? null, 2);
            
            $unitCost = $this->resolveUnitCostFromTotal(
                $quantity,
                $totalCost,
                $validated['unit_cost'] ?? null
            );
            
            $balanceBefore = $this->decimal($tank->current_balance_liters, 3);
            $stockValueBefore = $this->decimal($tank->estimated_stock_value ?? 0, 2);
            
            $balanceAfter = $this->decimal($balanceBefore + $quantity, 3);
            $stockValueAfter = $this->decimal($stockValueBefore + ($totalCost ?? 0), 2);
            
            $averageUnitCostAfter = $balanceAfter > 0
                ? $this->decimal($stockValueAfter / $balanceAfter, 4)
                : 0;
            $responsibleUserId = $validated['responsible_user_id'] ?? $context['user']->id;

            if ($balanceAfter > (float) $tank->capacity_liters) {
                throw ValidationException::withMessages([
                    'quantity_liters' => 'O recebimento ultrapassa a capacidade do tanque.',
                ]);
            }

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
                'supplier_id' => $validated['supplier_id'] ?? null,
                'invoice_number' => $validated['invoice_number'] ?? null,
                'responsible_user_id' => $responsibleUserId,
                'notes' => $validated['notes'] ?? null,
            ]);

            $tank->forceFill([
                'current_balance_liters' => $balanceAfter,
                'average_unit_cost' => $averageUnitCostAfter,
                'estimated_stock_value' => $stockValueAfter,
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
            'source' => ['nullable', 'string', 'in:internal_tank,external_station'],
            'fuel_tank_id' => ['nullable', 'integer'],
            'fuel_product_id' => ['nullable', 'integer'],
            'vehicle_id' => ['required', 'integer'],
            'driver_id' => ['nullable', 'integer'],
            'filled_at' => ['required', 'date'],
            'vehicle_km' => ['nullable', 'numeric', 'min:0'],
            'vehicle_hours' => ['nullable', 'numeric', 'min:0'],
            'quantity_liters' => ['required', 'numeric', 'gt:0'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'total_cost' => ['nullable', 'numeric', 'min:0'],
            'supplier_name' => ['nullable', 'string', 'max:255'],
            'supplier_id' => ['nullable', 'integer'],
            'document_number' => ['nullable', 'string', 'max:255'],
            'responsible_user_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string'],
            'confirm_high_vehicle_km' => ['nullable', 'boolean'],
            'confirm_high_vehicle_hours' => ['nullable', 'boolean'],
            'km_reading_confirmed' => ['nullable', 'boolean'],
            'hours_reading_confirmed' => ['nullable', 'boolean'],
        ])->validate();

        $source = $validated['source'] ?? FuelFilling::SOURCE_INTERNAL_TANK;

        if ($source === FuelFilling::SOURCE_INTERNAL_TANK && empty($validated['fuel_tank_id'])) {
            throw ValidationException::withMessages([
                'fuel_tank_id' => 'Informe o tanque da unidade para abastecimento interno.',
            ]);
        }

        if ($source === FuelFilling::SOURCE_EXTERNAL_STATION && empty($validated['fuel_product_id'])) {
            throw ValidationException::withMessages([
                'fuel_product_id' => 'Informe o produto abastecido no posto externo.',
            ]);
        }

        if ($source === FuelFilling::SOURCE_EXTERNAL_STATION) $validated=array_merge($validated,app(SupplierSnapshotService::class)->resolve($context['tenant_id'],$validated['supplier_id']??null,$validated['supplier_name']??null));
        return DB::transaction(function () use ($context, $validated, $source) {
            $vehicle = $this->vehicleForContext((int) $validated['vehicle_id'], $context);
            $this->validateVehicleCounters($vehicle, $validated);
            $this->validateDriverForContext($validated['driver_id'] ?? null, $context);

            $quantity = $this->decimal($validated['quantity_liters'], 3);
            $responsibleUserId = $validated['responsible_user_id'] ?? $context['user']->id;
            $movement = null;
            $tank = null;
            $balanceBefore = null;
            $balanceAfter = null;

            if ($source === FuelFilling::SOURCE_INTERNAL_TANK) {
                $tank = $this->lockTankForContext((int) $validated['fuel_tank_id'], $context);
                $this->ensureProductMatchesTank($tank, $validated['fuel_product_id'] ?? null);

                $unitCost = $this->decimal($tank->average_unit_cost ?? 0, 4);
                $totalCost = $this->decimal($quantity * $unitCost, 2);
                $fuelProductId = $tank->fuel_product_id;
                $balanceBefore = $this->decimal($tank->current_balance_liters, 3);

                if ($quantity > $balanceBefore) {
                    throw ValidationException::withMessages([
                        'quantity_liters' => 'A quantidade abastecida não pode ser maior que o saldo atual do tanque.',
                    ]);
                }

                $balanceAfter = $this->decimal($balanceBefore - $quantity, 3);
                $stockValueBefore = $this->decimal($tank->estimated_stock_value ?? 0, 2);
                $stockValueAfter = $this->decimal(max(0, $stockValueBefore - $totalCost), 2);
                $averageUnitCostAfter = $balanceAfter > 0
                    ? $this->decimal($stockValueAfter / $balanceAfter, 4)
                    : 0;
            } else {
                $product = $this->productForContext((int) $validated['fuel_product_id'], $context);
                $fuelProductId = $product->id;
                $totalCost = $this->nullableDecimal($validated['total_cost'] ?? null, 2);
                $unitCost = $this->resolveUnitCostFromTotal($quantity, $totalCost, $validated['unit_cost'] ?? null);

                if ($totalCost === null && $unitCost !== null) {
                    $totalCost = $this->decimal($quantity * $unitCost, 2);
                }
            }

            $filling = FuelFilling::query()->create([
                'tenant_id' => $context['tenant_id'],
                'division_id' => $context['division_id'],
                'location_id' => $context['location_id'],
                'fuel_tank_id' => $tank?->id,
                'fuel_product_id' => $fuelProductId,
                'source' => $source,
                'vehicle_id' => $vehicle->id,
                'driver_id' => $validated['driver_id'] ?? null,
                'filled_at' => Carbon::parse($validated['filled_at']),
                'vehicle_km' => $validated['vehicle_km'] ?? null,
                'vehicle_hours' => $validated['vehicle_hours'] ?? null,
                'quantity_liters' => $quantity,
                'unit_cost' => $unitCost,
                'total_cost' => $totalCost,
                'supplier_name' => $source === FuelFilling::SOURCE_EXTERNAL_STATION ? ($validated['supplier_name'] ?? null) : null,
                'supplier_id' => $source === FuelFilling::SOURCE_EXTERNAL_STATION ? ($validated['supplier_id'] ?? null) : null,
                'document_number' => $source === FuelFilling::SOURCE_EXTERNAL_STATION ? ($validated['document_number'] ?? null) : null,
                'responsible_user_id' => $responsibleUserId,
                'notes' => $validated['notes'] ?? null,
            ]);

            $this->updateVehicleCountersFromFilling(
                $vehicle,
                $validated,
                $context,
                $filling
            );

            if ($source === FuelFilling::SOURCE_INTERNAL_TANK && $tank) {
                $tank->forceFill([
                    'current_balance_liters' => $balanceAfter,
                    'average_unit_cost' => $averageUnitCostAfter,
                    'estimated_stock_value' => $stockValueAfter,
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
            }

            $summary = $source === FuelFilling::SOURCE_EXTERNAL_STATION
                ? "Abastecimento externo de {$quantity} litros registrado para o veículo {$vehicle->name}."
                : "Abastecimento de {$quantity} litros registrado para o veículo {$vehicle->name}.";

            $this->auditLog->created($filling, [
                'tenant_id' => $context['tenant_id'],
                'division_id' => $context['division_id'],
                'location_id' => $context['location_id'],
                'module' => 'fuel',
                'summary' => $summary,
                'after_data' => $filling->toArray(),
                'metadata' => [
                    'fuel_movement_id' => $movement?->id,
                    'fuel_tank_id' => $tank?->id,
                    'fuel_product_id' => $fuelProductId,
                    'vehicle_id' => $vehicle->id,
                    'source' => $source,
                    'supplier_name' => $filling->supplier_name,
                    'document_number' => $filling->document_number,
                    'balance_before' => $balanceBefore,
                    'balance_after' => $balanceAfter,
                ],
            ]);

            return $filling;
        });
    }
    private function updateVehicleCountersFromFilling(
        Vehicle $vehicle,
        array $validated,
        array $context,
        FuelFilling $filling
    ): void {
        if (array_key_exists('vehicle_km', $validated) && $validated['vehicle_km'] !== null) {
            $newKm = $this->decimal($validated['vehicle_km'], 0);

            $this->vehicleReadingService->updateKm(
                $vehicle,
                $newKm,
                $context['user'],
                'fuel_filling',
                "Hodômetro atualizado automaticamente pelo abastecimento #{$filling->id}.",
                'vehicle_km',
                ! empty($validated['km_reading_confirmed'])
                    || ! empty($validated['confirm_high_vehicle_km']),
                $filling->filled_at,
                $filling,
            );
        }
    
        if (array_key_exists('vehicle_hours', $validated) && $validated['vehicle_hours'] !== null) {
            $newHours = $this->decimal($validated['vehicle_hours'], 1);

            $this->vehicleReadingService->updateHours(
                $vehicle,
                $newHours,
                $context['user'],
                'fuel_filling',
                "Horímetro atualizado automaticamente pelo abastecimento #{$filling->id}.",
                'vehicle_hours',
                ! empty($validated['hours_reading_confirmed'])
                    || ! empty($validated['confirm_high_vehicle_hours']),
                $filling->filled_at,
                $filling,
            );
        }
    }

    public function cancelFilling(FuelFilling $filling, string $reason): void
    {
        $context = $this->resolveContext();
        $this->ensureRecordInContext($filling, $context);

        DB::transaction(function () use ($filling, $reason, $context) {
            $filling = FuelFilling::query()->lockForUpdate()->findOrFail($filling->id);
            if ($filling->cancelled_at) {
                throw ValidationException::withMessages(['filling' => 'Este abastecimento já foi cancelado.']);
            }

            if ($filling->resolved_source === FuelFilling::SOURCE_INTERNAL_TANK) {
                $tank = $this->lockTankForContext((int) $filling->fuel_tank_id, $context);
                $before = $this->decimal($tank->current_balance_liters, 3);
                $after = $this->decimal($before + (float) $filling->quantity_liters, 3);
                if ($after > (float) $tank->capacity_liters) {
                    throw ValidationException::withMessages(['filling' => 'O estorno ultrapassaria a capacidade atual do tanque.']);
                }
                $stockValue = $this->decimal((float) ($tank->estimated_stock_value ?? 0) + (float) ($filling->total_cost ?? 0), 2);
                $tank->forceFill(['current_balance_liters' => $after, 'estimated_stock_value' => $stockValue, 'average_unit_cost' => $after > 0 ? $this->decimal($stockValue / $after, 4) : 0])->save();
                $this->createMovement($context, $tank, FuelMovement::TYPE_REVERSAL, (float) $filling->quantity_liters, $before, $after, $filling, $context['user']->id, 'Estorno do abastecimento #'.$filling->id);
            }

            $filling->forceFill(['cancelled_at' => now(), 'cancelled_by' => $context['user']->id, 'cancel_reason' => $reason])->save();
            $issue = 'Leitura desconsiderada devido ao cancelamento do abastecimento #'.$filling->id.'.';
            VehicleUpdateLog::query()->where('fuel_filling_id', $filling->id)->update(['reading_status' => VehicleUpdateLog::READING_STATUS_IGNORED, 'reading_issue' => $issue, 'reviewed_by' => $context['user']->id, 'reviewed_at' => now()]);
            $vehicle = Vehicle::query()->find($filling->vehicle_id);
            if ($vehicle) {
                $latest = app(VehicleReadingReconciliationService::class)->latestValid($vehicle);
                if ($latest) $vehicle->forceFill(['current_km' => $latest['km']])->save();
            }
            $this->auditLog->updated($filling, ['tenant_id' => $context['tenant_id'], 'division_id' => $context['division_id'], 'location_id' => $context['location_id'], 'module' => 'fuel', 'summary' => 'Abastecimento #'.$filling->id.' cancelado.', 'after_data' => $filling->fresh()->toArray()]);
        });
    }

    public function cancelReceipt(FuelReceipt $receipt, string $reason): void
    {
        $context = $this->resolveContext();
        $this->ensureRecordInContext($receipt, $context);
        DB::transaction(function () use ($receipt, $reason, $context) {
            $receipt = FuelReceipt::query()->lockForUpdate()->findOrFail($receipt->id);
            if ($receipt->cancelled_at) throw ValidationException::withMessages(['receipt' => 'Este recebimento já foi cancelado.']);
            $tank = $this->lockTankForContext((int) $receipt->fuel_tank_id, $context);
            $before = $this->decimal($tank->current_balance_liters, 3);
            if ($before < (float) $receipt->quantity_liters) throw ValidationException::withMessages(['receipt' => 'Não é possível cancelar: parte deste combustível já foi consumida e o saldo ficaria negativo.']);
            $receiptMovement = FuelMovement::query()->where('source_type', FuelReceipt::class)->where('source_id', $receipt->id)->orderBy('id')->first();
            if ($receiptMovement && FuelMovement::query()->where('fuel_tank_id', $tank->id)->where('movement_type', FuelMovement::TYPE_FILLING)->where('id', '>', $receiptMovement->id)->exists()) {
                throw ValidationException::withMessages(['receipt' => 'Não é possível cancelar: existem abastecimentos posteriores que podem ter consumido este recebimento.']);
            }
            $after = $this->decimal($before - (float) $receipt->quantity_liters, 3);
            $stockValueBefore = $this->decimal($tank->estimated_stock_value ?? 0, 2);
            $stockValue = $this->decimal($stockValueBefore - (float) ($receipt->total_cost ?? 0), 2);
            if ($stockValue < 0) throw ValidationException::withMessages(['receipt' => 'Não é possível cancelar: o valor estimado do estoque ficaria negativo.']);
            $tank->forceFill(['current_balance_liters' => $after, 'estimated_stock_value' => $stockValue, 'average_unit_cost' => $after > 0 ? $this->decimal($stockValue / $after, 4) : 0])->save();
            $this->createMovement($context, $tank, FuelMovement::TYPE_REVERSAL, (float) $receipt->quantity_liters, $before, $after, $receipt, $context['user']->id, 'Estorno do recebimento #'.$receipt->id);
            $receipt->forceFill(['cancelled_at' => now(), 'cancelled_by' => $context['user']->id, 'cancel_reason' => $reason])->save();
            $this->auditLog->updated($receipt, ['tenant_id' => $context['tenant_id'], 'division_id' => $context['division_id'], 'location_id' => $context['location_id'], 'module' => 'fuel', 'summary' => 'Recebimento #'.$receipt->id.' cancelado.', 'after_data' => $receipt->fresh()->toArray()]);
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

    private function ensureRecordInContext(FuelReceipt|FuelFilling $record, array $context): void
    {
        if ((int) $record->tenant_id !== (int) $context['tenant_id'] || (int) $record->division_id !== (int) $context['division_id'] || (int) $record->location_id !== (int) $context['location_id']) {
            abort(403);
        }
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

    private function productForContext(int $productId, array $context): FuelProduct
    {
        $product = FuelProduct::query()
            ->where('tenant_id', $context['tenant_id'])
            ->where('active', true)
            ->find($productId);

        if (! $product) {
            throw ValidationException::withMessages([
                'fuel_product_id' => 'Produto de combustível não encontrado para o tenant ativo.',
            ]);
        }

        return $product;
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

    private function validateDriverForContext(mixed $driverId, array $context): void
    {
        if ($driverId === null || $driverId === '') {
            return;
        }

        $exists = UserDivisionAccess::query()
            ->where('tenant_id', $context['tenant_id'])
            ->where('user_id', (int) $driverId)
            ->where('division_id', $context['division_id'])
            ->where('module', 'fleet')
            ->whereIn('profile', ['driver', 'motorista'])
            ->where('active', true)
            ->where(function ($query) use ($context) {
                $query
                    ->where('location_id', $context['location_id'])
                    ->orWhereNull('location_id');
            })
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'driver_id' => 'O motorista informado não está disponível para a unidade ativa.',
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

    private function resolveUnitCostFromTotal(
        float $quantity,
        ?float $totalCost,
        mixed $fallbackUnitCost
    ): ?float {
        if ($totalCost !== null && $totalCost > 0 && $quantity > 0) {
            return $this->decimal($totalCost / $quantity, 4);
        }
    
        return $this->nullableDecimal($fallbackUnitCost, 4);
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
