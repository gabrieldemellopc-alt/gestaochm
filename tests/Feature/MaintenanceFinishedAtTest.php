<?php

namespace Tests\Feature;

use Tests\TestCase;

class MaintenanceFinishedAtTest extends TestCase
{
    public function test_close_modal_sends_an_editable_prepopulated_finished_at(): void
    {
        $view = file_get_contents(resource_path('views/vehicle/maintenance-index.blade.php'));

        $this->assertStringContainsString('name="finished_at"', $view);
        $this->assertStringContainsString('type="datetime-local"', $view);
        $this->assertStringContainsString("old('finished_at', now()->format('Y-m-d\\TH:i'))", $view);
        $this->assertStringContainsString('required', $view);
    }

    public function test_close_validates_effective_finished_at_against_opening_and_now(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/MaintenanceController.php'));

        $this->assertStringContainsString("'finished_at' => [", $controller);
        $this->assertStringContainsString("'required'", $controller);
        $this->assertStringContainsString("'date'", $controller);
        $this->assertStringContainsString('->afterOrEqual($maintenance->started_at ?? $maintenance->created_at)', $controller);
        $this->assertStringContainsString('->beforeOrEqual(now())', $controller);
    }

    public function test_close_persists_the_informed_value_and_retains_a_defensive_fallback(): void
    {
        $service = file_get_contents(app_path('Services/MaintenanceService.php'));

        $this->assertStringContainsString('?string $finishedAt = null', $service);
        $this->assertStringContainsString('$effectiveFinishedAt = $finishedAt ? Carbon::parse($finishedAt) : now();', $service);
        $this->assertStringContainsString("'finished_at' => \$effectiveFinishedAt", $service);
    }

    public function test_reopen_clears_finished_at_for_a_new_effective_closure(): void
    {
        $service = file_get_contents(app_path('Services/MaintenanceService.php'));

        $reopen = substr($service, strpos($service, 'public static function reopen'));
        $this->assertStringContainsString("'finished_at' => null", $reopen);
    }
}
