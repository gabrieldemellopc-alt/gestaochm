<?php

namespace Tests\Feature;

use App\Models\MaintenanceRecordExtraCost;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MaintenanceExtraCostDateTest extends TestCase
{
    public function test_extra_cost_date_is_fillable_cast_and_has_legacy_fallback(): void
    {
        $dated = new MaintenanceRecordExtraCost(['cost_date' => '2026-08-12']);
        $legacy = new MaintenanceRecordExtraCost();
        $legacy->created_at = Carbon::parse('2026-08-11 09:40:00');

        $this->assertSame('2026-08-12', $dated->cost_date->format('Y-m-d'));
        $this->assertSame('2026-08-12', $dated->effective_cost_date->format('Y-m-d'));
        $this->assertSame('2026-08-11', $legacy->effective_cost_date->format('Y-m-d'));
    }

    public function test_create_and_update_require_and_persist_cost_date(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/MaintenanceController.php'));
        $service = file_get_contents(app_path('Services/MaintenanceService.php'));

        $this->assertSame(2, substr_count($controller, "'cost_date' => ['required', 'date']"));
        $this->assertGreaterThanOrEqual(2, substr_count($service, "'cost_date' => \$data['cost_date']"));
    }

    public function test_form_list_edit_and_pdf_show_cost_date(): void
    {
        $index = file_get_contents(resource_path('views/vehicle/maintenance-index.blade.php'));
        $edit = file_get_contents(resource_path('views/vehicle/partials/maintenance-edit-modals.blade.php'));
        $pdf = file_get_contents(resource_path('views/vehicle/pdf/maintenance-order.blade.php'));

        $this->assertStringContainsString('name="cost_date"', $index);
        $this->assertStringContainsString("old('cost_date', now()->format('Y-m-d'))", $index);
        $this->assertStringContainsString('Data do custo:', $index);
        $this->assertStringContainsString('x-model="extraCostForm.cost_date"', $edit);
        $this->assertStringContainsString('<th>Data do custo</th>', $pdf);
        $this->assertStringContainsString('$extraCost->effective_cost_date', $pdf);
    }
}
