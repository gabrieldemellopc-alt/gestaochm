<?php

namespace Tests\Unit;

use App\Models\Location;
use App\Models\Vehicle;
use App\Services\AggregatedVehiclePolicy;
use PHPUnit\Framework\TestCase;

class AggregatedVehiclePolicyMaintenanceTest extends TestCase
{
    public function test_only_aggregated_vehicles_are_restricted_when_the_location_disables_maintenance(): void
    {
        $policy = new AggregatedVehiclePolicy();
        $blockedLocation = new Location(['allow_aggregated_maintenance' => false]);
        $allowedLocation = new Location(['allow_aggregated_maintenance' => true]);

        $aggregated = new Vehicle(['fleet_relation' => Vehicle::FLEET_RELATION_AGGREGATED]);
        $internal = new Vehicle(['fleet_relation' => Vehicle::FLEET_RELATION_INTERNAL]);
        $rented = new Vehicle(['fleet_relation' => 'rented']);

        $this->assertFalse($policy->allowsMaintenance($aggregated, $blockedLocation));
        $this->assertSame(AggregatedVehiclePolicy::MAINTENANCE_RESTRICTION_MESSAGE, $policy->maintenanceRestrictionReason($aggregated, $blockedLocation));
        $this->assertTrue($policy->allowsMaintenance($aggregated, $allowedLocation));
        $this->assertNull($policy->maintenanceRestrictionReason($aggregated, $allowedLocation));
        $this->assertTrue($policy->allowsMaintenance($internal, $blockedLocation));
        $this->assertTrue($policy->allowsMaintenance($rented, $blockedLocation));
    }
}
