<?php

namespace Tests\Unit;

use App\Models\Vehicle;
use App\Models\VehicleUpdateLog;
use App\Services\Consistency\ConsistencyScanner;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use ReflectionMethod;
use Tests\TestCase;

class ConsistencyScannerTest extends TestCase
{
    public function test_unreliable_meter_is_excluded_for_its_counter_type(): void
    {
        $scanner = new ConsistencyScanner();
        $vehicle = new Vehicle(['km_meter_status' => Vehicle::METER_STATUS_UNRELIABLE, 'hours_meter_status' => Vehicle::METER_STATUS_NORMAL]);
        $method = new ReflectionMethod($scanner, 'unreliable'); $method->setAccessible(true);
        $this->assertTrue($method->invoke($scanner, $vehicle, 'km'));
        $this->assertFalse($method->invoke($scanner, $vehicle, 'hours'));
    }

    public function test_two_equal_readings_do_not_create_an_aggregate_alert(): void
    {
        $this->assertSame([], $this->repeatOccurrence(2));
    }

    public function test_three_equal_readings_on_different_days_create_one_aggregate_alert(): void
    {
        $alerts = $this->repeatOccurrence(3);
        $this->assertCount(1, $alerts);
        $this->assertSame('vehicle_repeated_reading', $alerts[0]['rule_key']);
        $this->assertSame([11, 12, 13], $alerts[0]['details']['log_ids']);
    }

    private function repeatOccurrence(int $count): array
    {
        $vehicle = new Vehicle(['tenant_id' => 1, 'division_id' => 1, 'location_id' => 1, 'km_meter_status' => 'normal']); $vehicle->setAttribute('id', 99);
        $logs = collect(range(1, $count))->map(function ($day, $index) use ($vehicle) {
            $log = new VehicleUpdateLog(['new_value' => '500', 'read_at' => Carbon::parse("2026-09-0{$day} 08:00:00")]); $log->setAttribute('id', 11 + $index);
            $log->setRelation('vehicle', $vehicle); return $log;
        });
        $scanner = new ConsistencyScanner(); $method = new ReflectionMethod($scanner, 'repeatOccurrence'); $method->setAccessible(true);
        return $method->invoke($scanner, $logs);
    }
}
