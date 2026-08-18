<?php

namespace Tests\Feature;

use App\Models\FuelFilling;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleUpdateLog;
use App\Services\VehicleReadingService;
use App\Services\FuelReadingSynchronizationService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class VehicleReadingMetadataTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function (Blueprint $table) { $table->id(); $table->string('name'); $table->timestamps(); });
        Schema::create('fuel_fillings', function (Blueprint $table) { $table->id(); $table->unsignedBigInteger('tenant_id')->nullable(); $table->unsignedBigInteger('division_id')->nullable(); $table->unsignedBigInteger('location_id')->nullable(); $table->unsignedBigInteger('vehicle_id')->nullable(); $table->dateTime('filled_at')->nullable(); $table->decimal('vehicle_km', 12, 2)->nullable(); $table->string('vehicle_km_status')->nullable(); $table->timestamp('cancelled_at')->nullable(); $table->timestamps(); });
        Schema::create('vehicles', function (Blueprint $table) { $table->id(); $table->unsignedBigInteger('division_id')->nullable(); $table->unsignedBigInteger('location_id')->nullable(); $table->string('operational_status')->default('operational'); $table->decimal('current_km', 12, 2)->nullable(); $table->timestamp('last_km_update_at')->nullable(); $table->timestamps(); });
        Schema::create('vehicle_update_logs', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('vehicle_id'); $table->unsignedBigInteger('user_id')->nullable(); $table->unsignedBigInteger('division_id')->nullable(); $table->unsignedBigInteger('location_id')->nullable(); $table->string('type'); $table->string('source')->nullable(); $table->dateTime('read_at')->nullable(); $table->unsignedBigInteger('fuel_filling_id')->nullable(); $table->string('old_value')->nullable(); $table->string('new_value')->nullable(); $table->text('observation')->nullable(); $table->timestamps(); $table->unique(['fuel_filling_id', 'type']);
        });
    }

    public function test_operational_km_updates_current_counter_and_records_effective_date(): void
    {
        [$vehicle, $user] = $this->vehicleAndUser(14000);
        $readAt = Carbon::parse('2026-08-18 09:30:00');

        $updated = app(VehicleReadingService::class)->updateKm($vehicle, 14100, $user, 'dashboard_quick_update', null, 'km', false, $readAt);

        $this->assertTrue($updated);
        $this->assertSame(14100.0, (float) $vehicle->fresh()->current_km);
        $this->assertSame($readAt->toDateTimeString(), VehicleUpdateLog::first()->read_at->toDateTimeString());
        $this->assertDatabaseHas('vehicle_update_logs', ['type' => 'km', 'old_value' => '14000', 'new_value' => '14100']);
    }

    public function test_operational_jump_still_requires_confirmation(): void
    {
        [$vehicle, $user] = $this->vehicleAndUser(14000);

        try {
            app(VehicleReadingService::class)->updateKm($vehicle, 14600, $user, 'dashboard_quick_update');
            $this->fail('A confirmação de salto deveria ser exigida.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('km', $exception->errors());
        }
    }

    public function test_filling_reading_is_linked_once_and_uses_filling_date(): void
    {
        [$vehicle, $user] = $this->vehicleAndUser(14000);
        $filling = FuelFilling::create();
        $readAt = Carbon::parse('2026-08-17 12:00:00');
        $service = app(VehicleReadingService::class);

        $this->assertTrue($service->updateKm($vehicle, 14100, $user, 'fuel_filling', null, 'vehicle_km', false, $readAt, $filling));
        $this->assertFalse($service->updateKm($vehicle, 14100, $user, 'fuel_filling', null, 'vehicle_km', false, $readAt, $filling));
        $this->assertSame(1, VehicleUpdateLog::where('fuel_filling_id', $filling->id)->where('type', 'km')->count());
        $this->assertSame($readAt->toDateTimeString(), VehicleUpdateLog::first()->read_at->toDateTimeString());
    }

    public function test_historical_reading_preserves_current_counter_and_last_update_date(): void
    {
        [$vehicle, $user] = $this->vehicleAndUser(14900, '2026-08-10 08:00:00');
        $filling = FuelFilling::create();
        $readAt = Carbon::parse('2026-07-15 12:00:00');

        $created = app(VehicleReadingService::class)->registerHistoricalKmReading($vehicle, 13500, $user, $readAt, 'fuel_filling_import', 'Leitura histórica.', $filling);

        $this->assertTrue($created);
        $vehicle->refresh();
        $this->assertSame(14900.0, (float) $vehicle->current_km);
        $this->assertSame('2026-08-10 08:00:00', Carbon::parse($vehicle->last_km_update_at)->toDateTimeString());
        $this->assertDatabaseHas('vehicle_update_logs', ['fuel_filling_id' => $filling->id, 'type' => 'km', 'new_value' => '13500']);
        $this->assertFalse(app(VehicleReadingService::class)->registerHistoricalKmReading($vehicle, 13500, $user, $readAt, 'fuel_filling_import', null, $filling));
    }

    public function test_synchronization_orders_readings_and_reports_regression_without_mutating_current_km(): void
    {
        [$vehicle, $user] = $this->vehicleAndUser(14900, '2026-08-10 08:00:00');
        $first = FuelFilling::create(['vehicle_id' => $vehicle->id, 'filled_at' => '2026-07-15 12:00:00', 'vehicle_km' => 13500]);
        $second = FuelFilling::create(['vehicle_id' => $vehicle->id, 'filled_at' => '2026-07-27 12:00:00', 'vehicle_km' => 14086]);
        $regressive = FuelFilling::create(['vehicle_id' => $vehicle->id, 'filled_at' => '2026-07-28 12:00:00', 'vehicle_km' => 14000]);
        $sync = app(FuelReadingSynchronizationService::class);
        $fillings = $sync->eligibleFillings(['vehicle_id' => $vehicle->id]);

        $this->assertSame(3, $fillings->count());
        $this->assertTrue($sync->anomalies($fillings)->contains('type', 'regression'));
        $this->assertSame(3, $sync->syncVehicle($vehicle, $fillings, $user));
        $this->assertSame(0, $sync->eligibleFillings(['vehicle_id' => $vehicle->id])->count());
        $this->assertSame(14900.0, (float) $vehicle->fresh()->current_km);
        $this->assertSame('2026-07-15 12:00:00', VehicleUpdateLog::where('fuel_filling_id', $first->id)->first()->read_at->toDateTimeString());
        $this->assertDatabaseMissing('vehicle_update_logs', ['fuel_filling_id' => $regressive->id, 'new_value' => '14900']);
        $this->assertSame(1, VehicleUpdateLog::where('fuel_filling_id', $second->id)->where('type', 'km')->count());
    }

    private function vehicleAndUser(float $km, ?string $lastUpdateAt = null): array
    {
        return [
            Vehicle::create(['current_km' => $km, 'last_km_update_at' => $lastUpdateAt]),
            User::create(['name' => 'Usuário de teste']),
        ];
    }
}
