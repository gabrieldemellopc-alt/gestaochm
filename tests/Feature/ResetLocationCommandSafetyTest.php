<?php

namespace Tests\Feature;

use Tests\TestCase;

class ResetLocationCommandSafetyTest extends TestCase
{
    public function test_reset_location_is_dry_run_by_default_and_requires_exact_confirmation(): void
    {
        $command = file_get_contents(app_path('Console/Commands/ResetLocation.php'));

        $this->assertStringContainsString("if (! \$this->option('commit'))", $command);
        $this->assertStringContainsString("--confirm-location deve coincidir exatamente com --location", $command);
        $this->assertStringContainsString("DB::transaction", $command);
        $this->assertStringContainsString('NENHUMA ALTERAÇÃO FOI REALIZADA', $command);
    }

    public function test_reset_location_keeps_identity_configuration_and_audit_domains_out_of_delete_order(): void
    {
        $command = file_get_contents(app_path('Console/Commands/ResetLocation.php'));

        foreach (['users', 'user_division_accesses', 'profile_permission_overrides', 'system_audit_logs', 'fuel_tanks', 'procedures'] as $preserved) {
            $this->assertStringContainsString("'{$preserved}'", $command);
        }

        $this->assertStringNotContainsString("delete('fuel_tanks'", $command);
        $this->assertStringNotContainsString("delete('procedures'", $command);
        $this->assertStringNotContainsString("delete('system_audit_logs'", $command);
    }

    public function test_reset_location_deletes_children_before_parents_and_preserves_cross_location_tire_references(): void
    {
        $command = file_get_contents(app_path('Console/Commands/ResetLocation.php'));

        $this->assertLessThan(
            strpos($command, "delete('maintenance_records'"),
            strpos($command, "delete('maintenance_historical_composition_items'")
        );
        $this->assertLessThan(
            strpos($command, "delete('stock_items'"),
            strpos($command, "delete('maintenance_material_usages'")
        );
        $this->assertStringContainsString('externalTireReferences', $command);
        $this->assertStringContainsString('SHARED RECORDS PRESERVED', $command);
        $this->assertStringContainsString('exclusiveTireIds', $command);
        $this->assertStringContainsString('assertOtherLocationBaseline', $command);
    }
}
