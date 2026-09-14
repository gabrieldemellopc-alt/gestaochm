<?php

namespace Tests\Feature;

use App\Models\FuelFilling;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleUpdateLog;
use App\Services\VehicleReadingService;
use App\Services\FuelReadingSynchronizationService;
use App\Services\VehicleReadingReconciliationService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class VehicleReadingMetadataTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::create('users', function (Blueprint $table) { $table->id(); $table->string('name'); $table->timestamps(); });
        Schema::create('fuel_fillings', function (Blueprint $table) { $table->id(); $table->unsignedBigInteger('tenant_id')->nullable(); $table->unsignedBigInteger('division_id')->nullable(); $table->unsignedBigInteger('location_id')->nullable(); $table->unsignedBigInteger('vehicle_id')->nullable(); $table->dateTime('filled_at')->nullable(); $table->decimal('vehicle_km', 12, 2)->nullable(); $table->string('vehicle_km_status')->nullable(); $table->timestamp('cancelled_at')->nullable(); $table->timestamps(); });
        Schema::create('vehicles', function (Blueprint $table) { $table->id(); $table->unsignedBigInteger('tenant_id')->nullable(); $table->unsignedBigInteger('division_id')->nullable(); $table->unsignedBigInteger('location_id')->nullable(); $table->string('operational_status')->default('operational'); $table->decimal('current_km', 12, 2)->nullable(); $table->decimal('current_hours', 12, 2)->nullable(); $table->timestamp('last_km_update_at')->nullable(); $table->timestamp('last_hours_update_at')->nullable(); $table->boolean('km_control_enabled')->default(true); $table->string('km_meter_status')->default('normal'); $table->boolean('hours_control_enabled')->default(false); $table->string('hours_meter_status')->default('normal'); $table->boolean('tire_control_enabled')->default(true); $table->timestamps(); });
        Schema::create('vehicle_update_logs', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('vehicle_id'); $table->unsignedBigInteger('user_id')->nullable(); $table->unsignedBigInteger('division_id')->nullable(); $table->unsignedBigInteger('location_id')->nullable(); $table->string('type'); $table->string('source')->nullable(); $table->dateTime('read_at')->nullable(); $table->unsignedBigInteger('fuel_filling_id')->nullable(); $table->string('old_value')->nullable(); $table->string('new_value')->nullable(); $table->text('observation')->nullable(); $table->string('reading_status')->nullable(); $table->text('reading_issue')->nullable(); $table->unsignedBigInteger('reviewed_by')->nullable(); $table->timestamp('reviewed_at')->nullable(); $table->timestamps(); $table->unique(['fuel_filling_id', 'type']);
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

    public function test_historical_km_never_mutates_any_vehicle_counter_or_timestamp(): void
    {
        foreach ([14000, 14900, 16000] as $historicalKm) {
            [$vehicle, $user] = $this->vehicleAndUser(14900, '2026-08-10 08:00:00');
            $vehicle->update(['current_hours' => 900, 'last_hours_update_at' => '2026-08-10 08:00:00']);
            $before = $this->vehicleSnapshot($vehicle);
            $filling = FuelFilling::create(['vehicle_id' => $vehicle->id, 'vehicle_km' => $historicalKm]);

            $this->assertTrue(app(VehicleReadingService::class)->registerHistoricalKmReading($vehicle, $historicalKm, $user, '2026-09-02 12:00:00', 'fuel_filling_import', 'Histórico', $filling));
            $this->assertSame($before, $this->vehicleSnapshot($vehicle));
            $this->assertDatabaseHas('vehicle_update_logs', ['fuel_filling_id' => $filling->id, 'type' => 'km', 'new_value' => (string) $historicalKm]);
            $this->assertSame((float) $historicalKm, (float) $filling->fresh()->vehicle_km);
        }
    }

    public function test_historical_hours_never_mutates_any_vehicle_counter_or_timestamp(): void
    {
        foreach ([800, 900, 1000] as $historicalHours) {
            [$vehicle, $user] = $this->vehicleAndUser(14900, '2026-08-10 08:00:00');
            $vehicle->update(['current_hours' => 900, 'last_hours_update_at' => '2026-08-10 08:00:00']);
            $before = $this->vehicleSnapshot($vehicle);
            $filling = FuelFilling::create(['vehicle_id' => $vehicle->id]);

            $this->assertTrue(app(VehicleReadingService::class)->registerHistoricalHoursReading($vehicle, $historicalHours, $user, '2026-09-02 12:00:00', 'fuel_filling_import', 'Histórico', $filling));
            $this->assertSame($before, $this->vehicleSnapshot($vehicle));
            $this->assertDatabaseHas('vehicle_update_logs', ['fuel_filling_id' => $filling->id, 'type' => 'hours', 'new_value' => (string) $historicalHours]);
        }
    }

    public function test_vva002_historical_hours_reading_preserves_the_complete_vehicle_snapshot(): void
    {
        [$vehicle, $user] = $this->vehicleAndUser(0, null);
        $vehicle->update([
            'current_hours' => 15,
            'last_hours_update_at' => '2026-08-23 23:58:48',
            'km_control_enabled' => false,
            'hours_control_enabled' => true,
        ]);
        $before = $this->vehicleSnapshot($vehicle);
        $filling = FuelFilling::create(['vehicle_id' => $vehicle->id]);

        $this->assertTrue(app(VehicleReadingService::class)->registerHistoricalHoursReading(
            $vehicle,
            15,
            $user,
            '2026-08-14 00:00:00',
            'fuel_filling_import',
            'VVA002 — BOB-CAT-02 — horímetro histórico 15.',
            $filling,
        ));

        $this->assertSame($before, $this->vehicleSnapshot($vehicle));
        $this->assertDatabaseHas('vehicle_update_logs', [
            'vehicle_id' => $vehicle->id,
            'fuel_filling_id' => $filling->id,
            'type' => 'hours',
            'new_value' => '15',
        ]);
    }

    public function test_confirming_current_km_creates_an_auditable_log_without_changing_the_counter(): void
    {
        [$vehicle, $user] = $this->vehicleAndUser(596201, '2026-08-01 08:00:00');
        $before = $this->vehicleSnapshot($vehicle);
        $at = Carbon::parse('2026-09-09 10:00:00');

        $this->assertTrue(app(VehicleReadingService::class)->confirmCurrentKm($vehicle, $user, $at));

        $after = $this->vehicleSnapshot($vehicle);
        $this->assertSame($before['current_km'], $after['current_km']);
        $this->assertSame($before['current_hours'], $after['current_hours']);
        $this->assertSame('2026-09-09 10:00:00', $after['last_km_update_at']);
        $this->assertDatabaseHas('vehicle_update_logs', [
            'vehicle_id' => $vehicle->id,
            'type' => 'km',
            'source' => 'quick_update_confirmation',
            'old_value' => '596201',
            'new_value' => '596201',
        ]);
    }

    public function test_confirming_current_hours_creates_an_auditable_log_without_changing_the_counter(): void
    {
        [$vehicle, $user] = $this->vehicleAndUser(0, null);
        $vehicle->update(['current_hours' => 15, 'last_hours_update_at' => '2026-08-01 08:00:00']);
        $before = $this->vehicleSnapshot($vehicle);

        $this->assertTrue(app(VehicleReadingService::class)->confirmCurrentHours($vehicle, $user, Carbon::parse('2026-09-09 10:00:00')));

        $after = $this->vehicleSnapshot($vehicle);
        $this->assertSame($before['current_hours'], $after['current_hours']);
        $this->assertSame('2026-09-09 10:00:00', $after['last_hours_update_at']);
        $this->assertDatabaseHas('vehicle_update_logs', [
            'vehicle_id' => $vehicle->id,
            'type' => 'hours',
            'source' => 'quick_update_confirmation',
            'old_value' => '15',
            'new_value' => '15',
        ]);
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

    public function test_reconciliation_uses_latest_valid_effective_reading_and_can_reduce_current_km(): void
    {
        [$vehicle] = $this->vehicleAndUser(13200, '2026-08-04 19:05:50');
        $valid = FuelFilling::create(['vehicle_id' => $vehicle->id, 'filled_at' => '2026-07-27 12:00:00', 'vehicle_km' => 11058]);
        VehicleUpdateLog::create(['vehicle_id' => $vehicle->id, 'type' => 'km', 'new_value' => 13200, 'read_at' => '2026-08-04 19:05:50', 'reading_status' => VehicleUpdateLog::READING_STATUS_IGNORED]);

        $reading = app(VehicleReadingReconciliationService::class)->latestValid($vehicle);

        $this->assertSame(11058.0, $reading['km']);
        $this->assertSame($valid->id, $reading['fuel_filling_id']);
        $this->assertSame('2026-07-27 12:00:00', $reading['date']->toDateTimeString());
        $this->assertSame(1, VehicleUpdateLog::count(), 'A análise não deve criar logs.');
    }

    public function test_reconciliation_keeps_null_and_valid_logs_but_ignores_suspect_ones(): void
    {
        [$vehicle] = $this->vehicleAndUser(1000);
        VehicleUpdateLog::create(['vehicle_id' => $vehicle->id, 'type' => 'km', 'new_value' => 1100, 'read_at' => '2026-07-01 12:00:00']);
        VehicleUpdateLog::create(['vehicle_id' => $vehicle->id, 'type' => 'km', 'new_value' => 1200, 'read_at' => '2026-07-02 12:00:00', 'reading_status' => VehicleUpdateLog::READING_STATUS_VALID]);
        VehicleUpdateLog::create(['vehicle_id' => $vehicle->id, 'type' => 'km', 'new_value' => 9999, 'read_at' => '2026-07-03 12:00:00', 'reading_status' => VehicleUpdateLog::READING_STATUS_SUSPECT]);

        $reading = app(VehicleReadingReconciliationService::class)->latestValid($vehicle);

        $this->assertSame(1200.0, $reading['km']);
        $this->assertSame('2026-07-02 12:00:00', $reading['date']->toDateTimeString());
    }

    public function test_coherent_backdated_reading_is_recorded_without_regressing_current_counter(): void
    {
        [$vehicle, $user] = $this->vehicleAndUser(152000, '2026-09-10 08:00:00');
        VehicleUpdateLog::create(['vehicle_id' => $vehicle->id, 'type' => 'km', 'new_value' => 151000, 'read_at' => '2026-09-05 08:00:00']);
        VehicleUpdateLog::create(['vehicle_id' => $vehicle->id, 'type' => 'km', 'new_value' => 152000, 'read_at' => '2026-09-10 08:00:00']);

        $this->assertTrue(app(VehicleReadingService::class)->updateKm($vehicle, 151400, $user, 'fuel_filling', null, 'vehicle_km', false, '2026-09-07 08:00:00'));
        $vehicle->refresh();

        $this->assertSame(152000.0, (float) $vehicle->current_km);
        $this->assertSame('2026-09-10 08:00:00', Carbon::parse($vehicle->last_km_update_at)->toDateTimeString());
        $this->assertDatabaseHas('vehicle_update_logs', ['vehicle_id' => $vehicle->id, 'new_value' => '151400', 'reading_status' => VehicleUpdateLog::READING_STATUS_VALID]);
    }

    public function test_inconsistent_backdated_reading_requires_confirmation_then_is_suspect(): void
    {
        [$vehicle, $user] = $this->vehicleAndUser(152000, '2026-09-10 08:00:00');
        VehicleUpdateLog::create(['vehicle_id' => $vehicle->id, 'type' => 'km', 'new_value' => 151000, 'read_at' => '2026-09-05 08:00:00']);
        VehicleUpdateLog::create(['vehicle_id' => $vehicle->id, 'type' => 'km', 'new_value' => 152000, 'read_at' => '2026-09-10 08:00:00']);

        try {
            app(VehicleReadingService::class)->updateKm($vehicle, 149000, $user, 'fuel_filling', null, 'vehicle_km', false, '2026-09-07 08:00:00');
            $this->fail('A confirmação cronológica deveria ser exigida.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('Anterior:', $exception->errors()['vehicle_km'][0]);
            $this->assertStringContainsString('Posterior:', $exception->errors()['vehicle_km'][0]);
        }

        $this->assertTrue(app(VehicleReadingService::class)->updateKm($vehicle, 149000, $user, 'fuel_filling', null, 'vehicle_km', true, '2026-09-07 08:00:00'));
        $this->assertSame(152000.0, (float) $vehicle->fresh()->current_km);
        $this->assertDatabaseHas('vehicle_update_logs', ['vehicle_id' => $vehicle->id, 'new_value' => '149000', 'reading_status' => VehicleUpdateLog::READING_STATUS_SUSPECT]);
    }

    public function test_hours_use_the_same_backdated_timeline_rule(): void
    {
        [$vehicle, $user] = $this->vehicleAndUser(0);
        $vehicle->update(['current_hours' => 152, 'last_hours_update_at' => '2026-09-10 08:00:00']);
        VehicleUpdateLog::create(['vehicle_id' => $vehicle->id, 'type' => 'hours', 'new_value' => 151, 'read_at' => '2026-09-05 08:00:00']);
        VehicleUpdateLog::create(['vehicle_id' => $vehicle->id, 'type' => 'hours', 'new_value' => 152, 'read_at' => '2026-09-10 08:00:00']);

        $this->assertTrue(app(VehicleReadingService::class)->updateHours($vehicle, 151.4, $user, 'maintenance_open', null, 'performed_hours', false, '2026-09-07 08:00:00'));
        $this->assertSame(152.0, (float) $vehicle->fresh()->current_hours);
        $this->assertDatabaseHas('vehicle_update_logs', ['vehicle_id' => $vehicle->id, 'type' => 'hours', 'new_value' => '151.4', 'reading_status' => VehicleUpdateLog::READING_STATUS_VALID]);
    }

    public function test_fuel_filling_form_keeps_counter_minimums_technical_and_does_not_bind_them_to_current_readings(): void
    {
        $view = file_get_contents(resource_path('views/fuel/tanks/index.blade.php'));

        $this->assertMatchesRegularExpression('/name="vehicle_hours"\s+min="0"/s', $view);
        $this->assertMatchesRegularExpression('/name="vehicle_km"\s+min="0"/s', $view);
        $this->assertStringNotContainsString('kmInput.min = currentKm', $view);
        $this->assertStringNotContainsString('hoursInput.min = currentHours', $view);
        $this->assertStringNotContainsString('informedKm < currentKm', $view);
        $this->assertStringNotContainsString('informedHours < currentHours', $view);
    }

    public function test_unreliable_meter_records_a_suspect_reading_without_promoting_the_counter(): void
    {
        [$vehicle, $user] = $this->vehicleAndUser(2500, '2026-09-10 08:00:00');
        $vehicle->setAttribute('km_meter_status', Vehicle::METER_STATUS_UNRELIABLE);

        $this->assertTrue(app(VehicleReadingService::class)->updateKm($vehicle, 2700, $user, 'fuel_filling', null, 'vehicle_km', false, '2026-09-11 08:00:00'));
        $this->assertSame(2500.0, (float) $vehicle->fresh()->current_km);
        $this->assertDatabaseHas('vehicle_update_logs', ['vehicle_id' => $vehicle->id, 'new_value' => '2700', 'reading_status' => VehicleUpdateLog::READING_STATUS_SUSPECT]);
    }

    private function vehicleAndUser(float $km, ?string $lastUpdateAt = null): array
    {
        return [
            Vehicle::create(['current_km' => $km, 'last_km_update_at' => $lastUpdateAt]),
            User::create(['name' => 'Usuário de teste']),
        ];
    }

    private function vehicleSnapshot(Vehicle $vehicle): array
    {
        $vehicle->refresh();
        return [
            'current_km' => (string) $vehicle->current_km,
            'current_hours' => (string) $vehicle->current_hours,
            'last_km_update_at' => $vehicle->last_km_update_at ? Carbon::parse($vehicle->last_km_update_at)->toDateTimeString() : null,
            'last_hours_update_at' => $vehicle->last_hours_update_at ? Carbon::parse($vehicle->last_hours_update_at)->toDateTimeString() : null,
            'updated_at' => $vehicle->updated_at ? Carbon::parse($vehicle->updated_at)->toDateTimeString() : null,
        ];
    }
}
