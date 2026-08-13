<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MaintenanceEditingConfigurationTest extends TestCase
{
    public function test_editing_routes_are_registered_with_patch_method(): void
    {
        $itemRoute = app('router')->getRoutes()->getByName('vehicles.maintenance.items.update');
        $extraCostRoute = app('router')->getRoutes()->getByName('vehicles.maintenance.extra-costs.update');
        $replaceRoute = app('router')->getRoutes()->getByName('vehicles.maintenance.items.replace');

        $this->assertNotNull($itemRoute);
        $this->assertNotNull($extraCostRoute);
        $this->assertContains('PATCH', $itemRoute->methods());
        $this->assertContains('PATCH', $extraCostRoute->methods());
        $this->assertNotNull($replaceRoute);
        $this->assertContains('POST', $replaceRoute->methods());
    }

    public function test_supervisor_editing_permissions_are_disabled_by_default(): void
    {
        $permissions = config('chm_permissions.groups.maintenance.permissions');

        $this->assertFalse($permissions['maintenance.edit_items']['default']['supervisor']);
        $this->assertFalse($permissions['maintenance.edit_extra_costs']['default']['supervisor']);
    }

    public function test_maintenance_items_have_replacement_columns(): void
    {
        $originalConnection = DB::getDefaultConnection();
        Config::set('database.connections.maintenance_schema_test', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        DB::setDefaultConnection('maintenance_schema_test');

        try {
            Schema::create('users', fn ($table) => $table->id());
            Schema::create('maintenance_records', fn ($table) => $table->id());
            Schema::create('procedures', fn ($table) => $table->id());

            $baseMigration = require database_path('migrations/2026_06_28_074521_create_maintenance_record_items_table.php');
            $baseMigration->up();
            $replacementMigration = require database_path('migrations/2026_08_07_120000_add_cancellation_and_replacement_fields_to_maintenance_record_items.php');
            $replacementMigration->up();

            $this->assertTrue(Schema::hasColumns('maintenance_record_items', [
                'cancelled_at',
                'cancelled_by',
                'cancel_reason',
                'cancellation_type',
                'replaced_by_item_id',
                'replacement_of_item_id',
            ]));
        } finally {
            DB::purge('maintenance_schema_test');
            DB::setDefaultConnection($originalConnection);
        }
    }

    public function test_replacement_reverses_stock_before_creating_the_new_item(): void
    {
        $service = file_get_contents(app_path('Services/MaintenanceService.php'));
        $reversePosition = strpos($service, '$reversedMovements = self::reverseItemStock(');
        $addPosition = strpos($service, '$newItem = self::addItem($maintenance, $data, true);');

        $this->assertNotFalse($reversePosition);
        $this->assertNotFalse($addPosition);
        $this->assertLessThan($addPosition, $reversePosition);
        $this->assertStringContainsString(
            'Estoque insuficiente para o novo lançamento após considerar a devolução do serviço atual.',
            $service
        );
    }

    public function test_replacement_copy_is_operational_and_discreet(): void
    {
        $view = file_get_contents(resource_path('views/vehicle/maintenance-add-item.blade.php'));

        $this->assertStringContainsString('Corrigir serviço lançado', $view);
        $this->assertStringContainsString('Confirmo a correção deste lançamento.', $view);
        $this->assertStringNotContainsString('Correção com preservação do histórico', $view);
    }

    public function test_replacement_requires_reason_and_confirmation(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/MaintenanceController.php'));

        $this->assertStringContainsString("'change_reason' => ['required', 'string', 'min:10', 'max:2000']", $controller);
        $this->assertStringContainsString("'confirm_replacement' => ['accepted']", $controller);
    }

    public function test_add_item_reason_is_required_and_limited_to_supported_values(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/MaintenanceController.php'));
        $view = file_get_contents(resource_path('views/vehicle/maintenance-add-item.blade.php'));

        $this->assertGreaterThanOrEqual(
            2,
            substr_count($controller, "'reason' => ['required', 'in:preventive,corrective,inspection,other']")
        );
        $this->assertStringContainsString("'reason.required' => 'Selecione o motivo da manutenção.'", $controller);
        $this->assertStringContainsString('type="radio"', $view);
        $this->assertStringContainsString("@checked(old('reason', 'preventive') === \$reasonValue)", $view);
        $this->assertStringContainsString('Dados — {{ $procedure->name }}', $view);
        $this->assertStringContainsString("Execução {{ \$executionType === 'internal' ? 'Oficina interna' : 'Terceirizada' }}", $view);
        $this->assertStringNotContainsString('maintenance-add-context-chip', $view);
        $this->assertStringNotContainsString('type="hidden" name="reason"', $view);
    }
}
