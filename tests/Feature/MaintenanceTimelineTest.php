<?php

namespace Tests\Feature;

use Tests\TestCase;

class MaintenanceTimelineTest extends TestCase
{
    public function test_timeline_uses_structured_material_and_photo_sources(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/VehicleController.php'));

        $this->assertStringContainsString('private function maintenanceTimeline', $controller);
        $this->assertStringContainsString('allMaterialUsages', $controller);
        foreach (['Material utilizado', 'Material corrigido', 'Material cancelado', 'Foto anexada', 'Foto removida', 'maintenance_photo_deleted'] as $text) {
            $this->assertStringContainsString($text, $controller);
        }
        $this->assertStringNotContainsString('maintenance_photo_upload_token_created', $controller);
    }

    public function test_timeline_is_sorted_and_permission_aware(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/VehicleController.php'));

        $this->assertStringContainsString("sortBy('at')->values()", $controller);
        $this->assertStringContainsString("\$permissions['view_costs']", $controller);
        $this->assertStringContainsString("\$permissions['cancel_materials']", $controller);
        $this->assertStringContainsString("\$permissions['view_cancellation_details']", $controller);
    }

    public function test_view_renders_normalized_events_without_old_separate_loops(): void
    {
        $view = file_get_contents(resource_path('views/vehicle/maintenance-index.blade.php'));

        $this->assertStringContainsString('@foreach($maintenanceTimeline as $event)', $view);
        $this->assertStringNotContainsString("@foreach(\$openMaintenance->statusLogs->sortBy('created_at') as \$log)", $view);
        $this->assertStringNotContainsString("@foreach(\$openMaintenance->extraCosts->sortBy('created_at') as \$extraCost)", $view);
    }
}
