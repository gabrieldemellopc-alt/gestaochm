<?php

namespace Tests\Feature;

use App\Models\FuelFilling;
use App\Models\Vehicle;
use App\Models\VehicleUpdateLog;
use App\Services\OperationalDashboardService;
use Illuminate\Support\Collection;
use ReflectionMethod;
use Tests\TestCase;

class OperationalDashboardServiceTest extends TestCase
{
    public function test_null_zero_and_one_km_are_invalid_sentinels(): void
    {
        foreach ([null, 0, 1] as $sentinel) {
            $average = $this->averages([
                $this->filling(1, '2026-09-01', 100, 100),
                $this->filling(1, '2026-09-02', $sentinel, 100),
                $this->filling(1, '2026-09-03', 300, 100),
            ])[1];

            $this->assertNull($average['value']);
            $this->assertSame('inconsistent', $average['status']);
        }
    }

    public function test_zero_delta_and_regression_do_not_produce_an_average(): void
    {
        foreach ([[100, 100], [200, 100]] as [$firstKm, $secondKm]) {
            $average = $this->averages([
                $this->filling(1, '2026-09-01', $firstKm, 100),
                $this->filling(1, '2026-09-02', $secondKm, 100),
            ])[1];

            $this->assertNull($average['value']);
            $this->assertSame('inconsistent', $average['status']);
        }
    }

    public function test_outlier_above_twenty_km_per_liter_is_excluded_but_plausible_interval_is_kept(): void
    {
        $average = $this->averages([
            $this->filling(1, '2026-09-01', 100, 10),
            $this->filling(1, '2026-09-02', 200, 10), // 10 km/L
            $this->filling(1, '2026-09-03', 1000, 10), // 80 km/L: outlier
        ])[1];

        $this->assertSame(10.0, $average['value']);
    }

    public function test_interval_at_exactly_twenty_km_per_liter_is_plausible(): void
    {
        $average = $this->averages([
            $this->filling(1, '2026-09-01', 100, 10),
            $this->filling(1, '2026-09-02', 300, 10),
        ])[1];

        $this->assertSame(20.0, $average['value']);
    }

    public function test_no_valid_interval_returns_not_available(): void
    {
        $average = $this->averages([
            $this->filling(1, '2026-09-01', null, 10),
            $this->filling(1, '2026-09-02', 1, 10),
            $this->filling(1, '2026-09-03', 0, 10),
        ])[1];

        $this->assertNull($average['value']);
        $this->assertSame('N/D', $average['formatted']);
    }

    public function test_existing_suspect_or_ignored_km_status_is_respected(): void
    {
        $average = $this->averages([
            $this->filling(1, '2026-09-01', 100, 10),
            $this->filling(1, '2026-09-02', 200, 10, FuelFilling::KM_STATUS_SUSPECT),
            $this->filling(1, '2026-09-03', 300, 10),
        ])[1];

        $this->assertNull($average['value']);
    }

    public function test_bxf_historical_outlier_is_excluded(): void
    {
        $average = $this->averages([
            $this->filling(59, '2026-08-17', 597027, 83.2),
            $this->filling(59, '2026-08-20', 596975, 65.2),
            $this->filling(59, '2026-08-25', 597245, 97.6),
            $this->filling(59, '2026-08-28', 597272, 93.5),
            $this->filling(59, '2026-08-31', 818453, 100),
        ])[59];

        $this->assertSame(1.55, $average['value']);
    }

    public function test_nxp_sentinel_km_is_excluded_without_promoting_current_km(): void
    {
        $average = $this->averages([
            $this->filling(89, '2026-08-11', 555885, 80),
            $this->filling(89, '2026-08-14', 1, 50),
            $this->filling(89, '2026-08-18', 556074, 118.3),
            $this->filling(89, '2026-09-01', 556846, 117.8),
        ])[89];

        $this->assertSame(6.55, $average['value']);
    }

    public function test_hours_only_vehicle_never_uses_historical_km_as_a_hours_fallback(): void
    {
        $average = $this->averages([
            $this->filling(80, '2026-09-01', 100, 30, null, null, 'retroescavadeira', null, false, true),
            $this->filling(80, '2026-09-02', 200, 30, null, null, 'retroescavadeira', null, false, true),
        ])[80];

        $this->assertNull($average['value']);
        $this->assertSame('N/D', $average['formatted']);
    }

    public function test_hours_only_vehicle_calculates_liters_per_hour_without_crossing_invalid_hours(): void
    {
        $average = $this->averages([
            $this->filling(80, '2026-09-01', null, 30, null, 10, 'retroescavadeira', null, false, true),
            $this->filling(80, '2026-09-02', null, 30, null, 0, 'retroescavadeira', null, false, true),
            $this->filling(80, '2026-09-03', null, 30, null, 20, 'retroescavadeira', null, false, true),
            $this->filling(80, '2026-09-04', null, 40, null, 30, 'retroescavadeira', null, false, true),
        ])[80];

        $this->assertSame(4.0, $average['value']);
        $this->assertSame('L/H', $average['unit']);
    }

    public function test_invalid_hours_status_is_not_used_for_hours_only_vehicle(): void
    {
        $average = $this->averages([
            $this->filling(80, '2026-09-01', null, 30, null, 10, 'trator', null, false, true),
            $this->filling(80, '2026-09-02', null, 30, null, 20, 'trator', VehicleUpdateLog::READING_STATUS_IGNORED, false, true),
        ])[80];

        $this->assertNull($average['value']);
    }

    private function averages(array $fillings): array
    {
        $service = (new \ReflectionClass(OperationalDashboardService::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod(OperationalDashboardService::class, 'vehicleFuelAverages');

        return $method->invoke($service, new Collection($fillings));
    }

    public function test_both_controls_disabled_returns_nd(): void
    {
        $average = $this->averages([
            $this->filling(1, '2026-09-01', 100, 10, null, 10, null, null, false, false),
            $this->filling(1, '2026-09-02', 200, 10, null, 20, null, null, false, false),
        ])[1];

        $this->assertSame('N/D', $average['formatted']);
    }

    private function filling(int $vehicleId, string $filledAt, float|int|null $km, float $liters, ?string $status = null, float|int|null $hours = null, ?string $type = null, ?string $hoursStatus = null, ?bool $kmControl = null, ?bool $hoursControl = null): FuelFilling
    {
        $filling = new FuelFilling([
            'vehicle_id' => $vehicleId,
            'filled_at' => $filledAt,
            'vehicle_km' => $km,
            'vehicle_km_status' => $status,
            'vehicle_hours' => $hours,
            'quantity_liters' => $liters,
        ]);

        if ($type !== null) {
            $filling->setRelation('vehicle', new Vehicle(['type' => $type, 'km_control_enabled' => $kmControl ?? true, 'hours_control_enabled' => $hoursControl ?? false]));
        } elseif ($kmControl !== null || $hoursControl !== null) {
            $filling->setRelation('vehicle', new Vehicle(['km_control_enabled' => $kmControl ?? true, 'hours_control_enabled' => $hoursControl ?? false]));
        }
        if ($hoursStatus !== null) {
            $filling->setRelation('vehicleReadingLogs', collect([
                new VehicleUpdateLog(['type' => 'hours', 'reading_status' => $hoursStatus]),
            ]));
        }

        return $filling;
    }
}
