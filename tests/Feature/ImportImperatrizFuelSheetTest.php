<?php

namespace Tests\Feature;

use App\Console\Commands\ImportImperatrizFuelSheet;
use App\Models\Division;
use App\Models\FuelFilling;
use App\Models\FuelProduct;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\Vehicle;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ImportImperatrizFuelSheetTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('tenants', function (Blueprint $table) { $table->id(); $table->string('name'); $table->timestamps(); });
        Schema::create('divisions', function (Blueprint $table) { $table->id(); $table->unsignedBigInteger('tenant_id'); $table->string('name')->nullable(); $table->timestamps(); });
        Schema::create('locations', function (Blueprint $table) { $table->id(); $table->unsignedBigInteger('tenant_id'); $table->unsignedBigInteger('division_id'); $table->string('name')->nullable(); $table->boolean('active')->default(true); $table->timestamps(); });
        Schema::create('vehicles', function (Blueprint $table) { $table->id(); $table->unsignedBigInteger('tenant_id'); $table->unsignedBigInteger('division_id'); $table->unsignedBigInteger('location_id'); $table->string('name'); $table->string('plate')->nullable(); $table->string('type')->default('lixo'); $table->string('operational_status')->default('operational'); $table->string('asset_code')->nullable(); $table->timestamps(); });
        Schema::create('fuel_products', function (Blueprint $table) { $table->id(); $table->unsignedBigInteger('tenant_id'); $table->string('name'); $table->string('slug'); $table->string('unit')->default('litros'); $table->boolean('active')->default(true); $table->timestamps(); });
        Schema::create('fuel_fillings', function (Blueprint $table) { $table->id(); $table->unsignedBigInteger('tenant_id'); $table->unsignedBigInteger('division_id'); $table->unsignedBigInteger('location_id'); $table->unsignedBigInteger('fuel_product_id'); $table->unsignedBigInteger('vehicle_id'); $table->string('source')->nullable(); $table->dateTime('filled_at')->nullable(); $table->decimal('vehicle_km', 12, 2)->nullable(); $table->decimal('quantity_liters', 14, 3); $table->decimal('unit_cost', 12, 4)->nullable(); $table->decimal('total_cost', 14, 2)->nullable(); $table->string('supplier_name')->nullable(); $table->string('document_number')->nullable(); $table->text('notes')->nullable(); $table->timestamps(); });
    }
public function test_normalizes_plates_and_fleet_codes(): void
    {
        $this->assertSame('TMW6B79', ImportImperatrizFuelSheet::normalizePlate(' TMW-6B79 '));
        $this->assertSame('JAC2E37', ImportImperatrizFuelSheet::normalizePlate('JAC.2E37'));
        $this->assertSame('VCA001', ImportImperatrizFuelSheet::normalizeFleet('VCA 01'));
        $this->assertSame('VCL013', ImportImperatrizFuelSheet::normalizeFleet('VCL13'));
    }

    public function test_dry_run_matches_normalized_plates_fleet_and_inverted_columns_without_writing(): void
    {
        [$tenant, $division, $location] = $this->context();
        $tmw = $this->vehicle($tenant, $division, $location, 'VCA014', 'TMW-6B79');
        $jac = $this->vehicle($tenant, $division, $location, 'VCA006', 'JAC-2E37');
        $tms = $this->vehicle($tenant, $division, $location, 'VCA007', 'TMS-1J41');
        $this->vehicle($tenant, $division, $location, 'VCA001', 'TMO-8H32');
        $this->product($tenant);

        $file = $this->sheet([
            ['18/05/2026', 'TMW6B79', 'VCA14', 'TMW6B79'],
            ['18/05/2026', 'JAC2E37', 'VCA06', 'JAC2E37'],
            ['18/05/2026', 'VCA 01', 'VCA 01', 'MOTORISTA'],
            ['06/07/2026', 'VCA07', 'TMS1J41', 'JOSUÉ SENA'],
            ['18/05/2026', 'HPB4781', 'AGREGADO', 'HPB4781'],
        ]);

        $this->artisan('chm:import-imperatriz-fuel', $this->arguments($file, $tenant, $division, $location, ['--dry-run' => true]))
            ->expectsOutputToContain('importáveis')
            ->assertExitCode(0);

        $this->assertDatabaseCount('fuel_fillings', 0);
        $this->assertSame('TMW-6B79', $tmw->plate);
        $this->assertSame('JAC-2E37', $jac->plate);
        $this->assertSame('TMS-1J41', $tms->plate);
    }

    public function test_commit_writes_only_importable_rows_and_is_idempotent(): void
    {
        [$tenant, $division, $location] = $this->context();
        $vehicle = $this->vehicle($tenant, $division, $location, 'VCA014', 'TMW-6B79');
        $this->product($tenant);
        $file = $this->sheet([
            ['18/05/2026', 'TMW6B79', 'VCA14', 'TMW6B79'],
            ['18/05/2026', 'HPB4781', 'AGREGADO', 'HPB4781'],
        ]);
        $arguments = $this->arguments($file, $tenant, $division, $location, ['--commit' => true]);

        $this->artisan('chm:import-imperatriz-fuel', $arguments)->assertExitCode(0);
        $this->assertDatabaseCount('fuel_fillings', 1);
        $this->assertDatabaseHas('fuel_fillings', ['vehicle_id' => $vehicle->id]);
        $this->artisan('chm:import-imperatriz-fuel', $arguments)->assertExitCode(0);
        $this->assertDatabaseCount('fuel_fillings', 1);
    }

    private function context(): array
    {
        $tenant = Tenant::create(['name' => 'Teste']);
        $division = Division::create(['tenant_id' => $tenant->id, 'name' => 'Divisão']);
        $location = Location::create(['tenant_id' => $tenant->id, 'division_id' => $division->id, 'name' => 'Imperatriz', 'active' => true]);
        return [$tenant, $division, $location];
    }

    private function vehicle(Tenant $tenant, Division $division, Location $location, string $name, string $plate): Vehicle
    {
        return Vehicle::create(['tenant_id' => $tenant->id, 'division_id' => $division->id, 'location_id' => $location->id, 'name' => $name, 'plate' => $plate, 'type' => 'lixo']);
    }

    private function product(Tenant $tenant): void
    {
        FuelProduct::create(['tenant_id' => $tenant->id, 'name' => 'Diesel S10', 'slug' => 'diesel-s10', 'unit' => 'litros', 'active' => true]);
    }

    private function sheet(array $rows): string
    {
        $file = tempnam(sys_get_temp_dir(), 'imperatriz-fuel-');
        $header = "Data\tVeiculo\tFrota\tVeiculo2\tkm anterior\tKm atual\tPercorrido Km\tVolume (L)\tValor por Litro\tValor Total\tMedia Km";
        $lines = array_map(fn (array $row) => implode("\t", [...$row, '100', '110', '10', '10,5', 'R$ 6,50', 'R$ 68,25', '1,0']), $rows);
        file_put_contents($file, implode(PHP_EOL, [$header, ...$lines]));
        return $file;
    }

    private function arguments(string $file, Tenant $tenant, Division $division, Location $location, array $options = []): array
    {
        return ['file' => $file, '--tenant-id' => $tenant->id, '--division-id' => $division->id, '--location-id' => $location->id, ...$options];
    }
}