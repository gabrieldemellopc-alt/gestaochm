<?php

namespace Tests\Feature;

use Tests\TestCase;

class MaintenanceOpenTabsLayoutTest extends TestCase
{
    public function test_service_picker_uses_buttons_and_keeps_backend_field(): void
    {
        $view = file_get_contents(resource_path('views/vehicle/maintenance-index.blade.php'));

        $this->assertStringContainsString('class="maintenance-procedure-picker"', $view);
        $this->assertStringContainsString('class="maintenance-procedure-option"', $view);
        $this->assertStringContainsString('type="hidden" name="procedure_id" x-model="procedureId"', $view);
        $this->assertStringContainsString("old('procedure_id', '')", $view);
        $this->assertStringContainsString("'is-active': procedureId", $view);
        $this->assertStringContainsString('Nenhum procedimento disponível para este veículo.', $view);
    }

    public function test_status_and_cost_fields_are_rendered_without_progressive_disclosure(): void
    {
        $view = file_get_contents(resource_path('views/vehicle/maintenance-index.blade.php'));

        $this->assertStringContainsString('maintenance-form-grid--status', $view);
        $this->assertStringContainsString('maintenance-form-grid--cost', $view);
        $this->assertStringNotContainsString('x-show="opened || description.length"', $view);
        $this->assertStringNotContainsString('x-show="selectedStatus !== currentStatus"', $view);
    }

    public function test_open_order_uses_two_column_tab_layout_with_mobile_fallback(): void
    {
        $css = file_get_contents(public_path('css/pages/maintenance.css'));

        $this->assertStringContainsString('grid-template-columns: minmax(0, .88fr) minmax(0, 1.12fr);', $css);
        $this->assertStringContainsString('display: contents;', $css);
        $this->assertStringContainsString('.maintenance-procedure-option.is-active', $css);
        $this->assertStringContainsString("@media (max-width: 700px)", $css);
    }
}
