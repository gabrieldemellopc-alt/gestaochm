<?php

namespace Tests\Feature;

use Tests\TestCase;
use Carbon\Carbon;

class ImportImperatrizPendingFuelSupplementTest extends TestCase
{
    public function test_supplement_uses_historical_context_and_scalar_counter_snapshot_guard(): void
    {
        $command = file_get_contents(app_path('Console/Commands/ImportImperatrizPendingFuelSupplement.php'));

        $this->assertStringContainsString('new FuelOperationContext($user, $tank->tenant_id, $tank->division_id, 3, true, $batch->id)', $command);
        $this->assertStringContainsString("'vehicle_hours'=>\$row['hours']", $command);
        $this->assertStringContainsString("'vehicle_km'=>\$row['km']", $command);
        $this->assertStringContainsString('$after = $this->vehicleCounterSnapshot($vehicle->fresh())', $command);
        $this->assertStringContainsString("'before' => \$value", $command);
        $this->assertStringContainsString("'after' => \$after[\$key] ?? null", $command);
        $this->assertStringContainsString('JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES', $command);
        $this->assertStringContainsString("'updated_at' => \$this->normalizeDateTimeForSnapshot(\$vehicle->updated_at)", $command);
        $this->assertStringContainsString("Vehicle::create(['tenant_id'=>\$tank->tenant_id", $command);
        $this->assertStringContainsString("'operation_started_at'=>'2026-08-01'])->fresh()", $command);
        $this->assertStringContainsString('DB::transaction', $command);
        $this->assertStringContainsString('Saldo final divergente.', $command);
    }

    public function test_supplement_source_preserves_two_references_and_correct_hours_reading_for_vva002(): void
    {
        $source = file_get_contents(storage_path('app/imports/imperatriz_combustivel_suplementar_pendencias_2026-08-14_2026-09-01.csv'));

        $this->assertStringContainsString('IMP-20260814-FILL-COM-0871', $source);
        $this->assertStringContainsString('IMP-20260901-FILL-COM-1063', $source);
        $this->assertStringContainsString('horímetro histórico 15', $source);
        $this->assertStringContainsString(',79337.00,,', $source);
    }

    public function test_snapshot_normalizes_carbon_string_and_null_timestamps(): void
    {
        $method = new \ReflectionMethod(\App\Console\Commands\ImportImperatrizPendingFuelSupplement::class, 'normalizeDateTimeForSnapshot');
        $command = app(\App\Console\Commands\ImportImperatrizPendingFuelSupplement::class);

        $this->assertSame('2026-09-01 10:20:30.000000', $method->invoke($command, Carbon::parse('2026-09-01 10:20:30')));
        $this->assertSame('2026-09-01 10:20:30.000000', $method->invoke($command, '2026-09-01 10:20:30'));
        $this->assertNull($method->invoke($command, null));
    }
}
