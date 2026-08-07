<?php

namespace Tests\Feature;

use Tests\TestCase;

class MaintenanceEditingConfigurationTest extends TestCase
{
    public function test_editing_routes_are_registered_with_patch_method(): void
    {
        $itemRoute = app('router')->getRoutes()->getByName('vehicles.maintenance.items.update');
        $extraCostRoute = app('router')->getRoutes()->getByName('vehicles.maintenance.extra-costs.update');

        $this->assertNotNull($itemRoute);
        $this->assertNotNull($extraCostRoute);
        $this->assertContains('PATCH', $itemRoute->methods());
        $this->assertContains('PATCH', $extraCostRoute->methods());
    }

    public function test_supervisor_editing_permissions_are_disabled_by_default(): void
    {
        $permissions = config('chm_permissions.groups.maintenance.permissions');

        $this->assertFalse($permissions['maintenance.edit_items']['default']['supervisor']);
        $this->assertFalse($permissions['maintenance.edit_extra_costs']['default']['supervisor']);
    }
}
