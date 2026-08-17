<?php

namespace Tests\Feature;

use App\Http\Controllers\FuelTankController;
use App\Models\FuelFilling;
use App\Models\Vehicle;
use Illuminate\Support\Collection;
use ReflectionMethod;
use Tests\TestCase;

class FuelConsumptionDashboardTest extends TestCase
{
    public function test_vehicle_efficiency_uses_only_positive_distances_from_known_import_notes(): void
    {
        $vehicle = new Vehicle(['name' => 'VCA001', 'plate' => 'TMO-8H32']);
        $first = $this->filling(1, 100, 20, 'Importação histórica Imperatriz; percorrido=100;');
        $second = $this->filling(1, 50, 10, 'Importação histórica Imperatriz; percorrido=50;');
        $invalid = $this->filling(1, -5, 10, 'Importação histórica Imperatriz; percorrido=-5;');
        foreach ([$first, $second, $invalid] as $filling) $filling->setRelation('vehicle', $vehicle);

        $result = $this->invoke('vehicleEfficiency', collect([$first, $second, $invalid]))->first();

        $this->assertSame(150.0, $result['total_km']);
        $this->assertSame(30.0, $result['total_liters']);
        $this->assertSame(5.0, $result['km_per_liter']);
        $this->assertSame(2, $result['valid_entries']);
        $this->assertSame(1, $result['ignored_entries']);
    }

    public function test_distance_rejects_unknown_notes_and_absurd_values(): void
    {
        $this->assertNull($this->invoke('validImportedDistance', $this->filling(1, 30, 10, 'Observação livre; percorrido=30;')));
        $this->assertNull($this->invoke('validImportedDistance', $this->filling(1, 6000, 10, 'Importação histórica Imperatriz; percorrido=6000;')));
    }

    private function filling(int $vehicleId, float $distance, float $liters, string $notes): FuelFilling
    {
        return new FuelFilling(['vehicle_id' => $vehicleId, 'quantity_liters' => $liters, 'notes' => $notes]);
    }

    private function invoke(string $method, mixed ...$arguments): mixed
    {
        $reflection = new ReflectionMethod(FuelTankController::class, $method);
        return $reflection->invoke(new FuelTankController(), ...$arguments);
    }
}