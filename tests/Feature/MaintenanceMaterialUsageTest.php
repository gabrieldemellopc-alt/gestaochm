<?php

namespace Tests\Feature;

use App\Services\MaintenanceMaterialService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MaintenanceMaterialUsageTest extends TestCase
{
    public function test_material_routes_are_registered(): void
    {
        foreach (['search', 'store', 'direct.store', 'cancel', 'replace'] as $action) {
            $this->assertNotNull(app('router')->getRoutes()->getByName("vehicles.maintenance.materials.{$action}"));
        }
    }

    public function test_direct_purchase_can_reuse_an_active_item_in_the_same_context(): void
    {
        $service = file_get_contents(app_path('Services/MaintenanceMaterialService.php'));
        $controller = file_get_contents(app_path('Http/Controllers/MaintenanceController.php'));

        $this->assertStringContainsString("'stock_item_id' => ['nullable', 'integer']", $controller);
        $this->assertStringContainsString("->whereKey(\$data['stock_item_id'])", $service);
        $this->assertStringContainsString("->where('tenant_id', \$maintenance->tenant_id)->where('location_id', \$locationId)", $service);
        $this->assertStringContainsString("->where('active', true)->lockForUpdate()->first()", $service);
        $this->assertStringContainsString("if (! empty(\$data['stock_item_id']))", $service);
    }

    public function test_direct_purchase_requires_a_context_category_and_normalizes_units_for_new_items(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/MaintenanceController.php'));
        $service = file_get_contents(app_path('Services/MaintenanceMaterialService.php'));

        $this->assertStringContainsString("['UNID', 'L', 'KG', 'G', 'Outro']", $controller);
        $this->assertStringContainsString("'unit_other'", $controller);
        $this->assertStringContainsString("Informe uma categoria para o novo item", $service);
        $this->assertStringContainsString("where('tenant_id', \$maintenance->tenant_id)->exists()", $service);
    }

    public function test_direct_purchase_uses_total_cost_to_calculate_unit_cost(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/MaintenanceController.php'));
        $view = file_get_contents(resource_path('views/vehicle/partials/maintenance-materials-summary.blade.php'));

        $this->assertStringContainsString("round((float) \$data['total_cost'] / (int) \$data['quantity'], 2)", $controller);
        $this->assertStringContainsString('Custo unitário calculado', $view);
        $this->assertStringContainsString('Calculado automaticamente com base no custo total e na quantidade.', $view);
    }

    public function test_direct_purchase_keeps_the_entry_then_usage_sequence_atomic(): void
    {
        $service = file_get_contents(app_path('Services/MaintenanceMaterialService.php'));
        $entry = strpos($service, '$entries->record($item');
        $usage = strpos($service, '$this->createUsage($maintenance', $entry);

        $this->assertStringContainsString('return DB::transaction(function () use ($maintenance, $data, $user, $entries)', $service);
        $this->assertNotFalse($entry);
        $this->assertNotFalse($usage);
        $this->assertLessThan($usage, $entry);
    }

    public function test_direct_purchase_view_reuses_the_existing_stock_search(): void
    {
        $panel = file_get_contents(resource_path('views/vehicle/partials/maintenance-materials-summary.blade.php'));

        $this->assertStringContainsString('Comprar / lançar material direto', $panel);
        $this->assertStringContainsString("route('vehicles.maintenance.materials.search'", $panel);
        $this->assertStringContainsString('name="stock_item_id"', $panel);
        $this->assertStringContainsString('x-on:input.debounce.300ms="search()"', $panel);
    }

    public function test_permissions_separate_use_and_cancellation(): void
    {
        $permissions = config('chm_permissions.groups.maintenance.permissions');
        $this->assertTrue($permissions['maintenance.use_materials']['default']['supervisor']);
        $this->assertFalse($permissions['maintenance.cancel_materials']['default']['supervisor']);
    }

    public function test_vehicle_maintenance_index_exposes_material_permissions(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/VehicleController.php'));

        $this->assertStringContainsString("'use_materials' => \$can('maintenance.use_materials')", $controller);
        $this->assertStringContainsString("'cancel_materials' => \$can('maintenance.cancel_materials')", $controller);
    }

    public function test_empty_material_card_is_available_to_authorized_users(): void
    {
        $view = file_get_contents(resource_path('views/vehicle/maintenance-index.blade.php'));

        $this->assertStringContainsString(
            "\$openMaintenance->materialUsages->isNotEmpty() || (\$maintenancePermissions['use_materials'] ?? false)",
            $view
        );
        $list = file_get_contents(resource_path('views/vehicle/partials/maintenance-materials-list.blade.php'));
        $panel = file_get_contents(resource_path('views/vehicle/partials/maintenance-materials-summary.blade.php'));

        $this->assertStringContainsString('maintenance-materials-summary', $view);
        $this->assertStringContainsString('maintenance-tab-grid', $panel);
        $this->assertStringContainsString('Adicionar material', $panel);
        $this->assertStringContainsString('x-ref="materialsList"', $panel);
        $this->assertStringNotContainsString('Gerenciar materiais', $panel);
        $this->assertStringContainsString('Nenhum material utilizado registrado ainda.', $list);
    }

    public function test_material_actions_return_ajax_payloads_without_removing_traditional_fallback(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/MaintenanceController.php'));

        $this->assertStringContainsString('if ($request->expectsJson())', $controller);
        $this->assertStringContainsString("'list_html' => view('vehicle.partials.maintenance-materials-list'", $controller);
        $this->assertStringContainsString("'quantity_total'", $controller);
        $this->assertStringContainsString("'materials_total'", $controller);
        $this->assertStringContainsString("'maintenance_total'", $controller);
        $this->assertStringContainsString("return back()->with('success', 'Material adicionado com sucesso.');", $controller);
    }

    public function test_material_usage_schema_preserves_stock_and_replacement_history(): void
    {
        $original = DB::getDefaultConnection();
        Config::set('database.connections.material_schema_test', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ]);
        DB::setDefaultConnection('material_schema_test');
        try {
            foreach (['tenants', 'locations', 'maintenance_records', 'stock_items', 'stock_movements', 'users'] as $table) {
                Schema::create($table, fn ($blueprint) => $blueprint->id());
            }
            (require database_path('migrations/2026_08_12_000001_create_maintenance_material_usages_table.php'))->up();
            $this->assertTrue(Schema::hasColumns('maintenance_material_usages', [
                'stock_movement_id', 'quantity', 'unit_cost', 'total_cost', 'cancelled_at',
                'cancel_reason', 'reversal_movement_id', 'replaced_by_usage_id', 'replaces_usage_id',
            ]));
        } finally {
            DB::purge('material_schema_test');
            DB::setDefaultConnection($original);
        }
    }

    public function test_addition_locks_stock_checks_balance_and_creates_direct_movement(): void
    {
        $service = file_get_contents(app_path('Services/MaintenanceMaterialService.php'));
        $this->assertStringContainsString("->lockForUpdate()->firstOrFail()", $service);
        $this->assertStringContainsString("'maintenance_record_item_id' => null", $service);
        $this->assertStringContainsString('Saldo insuficiente', $service);
        $this->assertStringContainsString("'movement_type' => 'out'", $service);
    }

    public function test_cancellation_and_replacement_reverse_before_new_consumption(): void
    {
        $service = file_get_contents(app_path('Services/MaintenanceMaterialService.php'));
        $reverse = strpos($service, '$this->reverse($maintenance, $usage, $data[\'change_reason\'], $user);');
        $replacement = strpos($service, '$replacement = $this->createUsage($maintenance, $data, $user, $usage->id);');
        $this->assertNotFalse($reverse);
        $this->assertNotFalse($replacement);
        $this->assertLessThan($replacement, $reverse);
        $this->assertStringContainsString("'movement_type' => 'in'", $service);
        $this->assertStringContainsString("'reversal_movement_id' => \$reverse->id", $service);
    }

    public function test_total_only_adds_active_direct_materials_once(): void
    {
        $service = file_get_contents(app_path('Services/MaintenanceService.php'));
        $this->assertStringContainsString('MaintenanceMaterialUsage::query()', $service);
        $this->assertStringContainsString("->whereNull('cancelled_at')", $service);
        $this->assertStringContainsString('(float) $materialsTotal', $service);
        $this->assertStringNotContainsString("whereNull('maintenance_record_item_id')->sum('total_cost')", $service);
    }

    public function test_material_quantity_one_is_accepted(): void
    {
        $this->assertSame(1, MaintenanceMaterialService::validatedQuantity('1'));
    }

    public function test_material_quantity_zero_is_rejected(): void
    {
        $this->assertInvalidMaterialQuantity(0);
    }

    public function test_decimal_material_quantity_is_rejected(): void
    {
        $this->assertInvalidMaterialQuantity('0.01');
    }

    public function test_negative_material_quantity_is_rejected(): void
    {
        $this->assertInvalidMaterialQuantity(-1);
    }

    public function test_replacement_quantity_one_is_accepted(): void
    {
        $this->assertSame(1, MaintenanceMaterialService::validatedQuantity(1));
    }

    public function test_decimal_replacement_quantity_is_rejected(): void
    {
        $this->assertInvalidMaterialQuantity(1.5);
    }

    public function test_invalid_quantity_is_checked_before_stock_or_total_mutation(): void
    {
        $service = file_get_contents(app_path('Services/MaintenanceMaterialService.php'));
        $addValidation = strpos($service, "public function add(");
        $addTransaction = strpos($service, 'return DB::transaction', $addValidation);
        $addGuard = strpos($service, 'self::validatedQuantity', $addValidation);
        $replaceValidation = strpos($service, 'public function replace(');
        $replaceTransaction = strpos($service, 'return DB::transaction', $replaceValidation);
        $replaceGuard = strpos($service, 'self::validatedQuantity', $replaceValidation);

        $this->assertLessThan($addTransaction, $addGuard);
        $this->assertLessThan($replaceTransaction, $replaceGuard);
    }

    public function test_material_list_combines_direct_and_procedure_sources(): void
    {
        $list = file_get_contents(resource_path('views/vehicle/partials/maintenance-materials-list.blade.php'));

        $this->assertStringContainsString('$maintenance->materialUsages', $list);
        $this->assertStringContainsString('$maintenance->procedureMaterialMovements', $list);
        $this->assertStringContainsString('maintenance-material-entry--direct', $list);
        $this->assertStringContainsString('maintenance-material-entry--procedure', $list);
    }

    public function test_procedure_material_relation_only_includes_active_unreversed_out_movements(): void
    {
        $model = file_get_contents(app_path('Models/MaintenanceRecord.php'));

        $this->assertStringContainsString("->where('movement_type', 'out')", $model);
        $this->assertStringContainsString("->whereNotNull('maintenance_record_item_id')", $model);
        $this->assertStringContainsString("->whereNull('cancelled_at')", $model);
        $this->assertStringContainsString("->whereNull('reversal_movement_id')", $model);
        $this->assertStringContainsString("->whereNull('reversed_from_movement_id')", $model);
        $this->assertStringContainsString("->whereHas('maintenanceRecordItem', fn (\$query) => \$query->whereNull('cancelled_at'))", $model);
    }

    public function test_procedure_material_is_informative_and_identifies_its_procedure(): void
    {
        $list = file_get_contents(resource_path('views/vehicle/partials/maintenance-materials-list.blade.php'));
        $procedureBlock = substr($list, strpos($list, '@foreach($maintenance->procedureMaterialMovements'));

        $this->assertStringContainsString('maintenance-material-origin-badge', $procedureBlock);
        $this->assertStringContainsString('Procedimento: {{ $procedureName }}', $procedureBlock);
        $this->assertStringContainsString('Para corrigir, acesse o serviço/procedimento correspondente.', $procedureBlock);
        $this->assertStringNotContainsString('maintenance-materials-actions', $procedureBlock);
    }

    public function test_ajax_reload_eager_loads_procedure_materials_without_changing_direct_totals(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/MaintenanceController.php'));

        $this->assertStringContainsString("'procedureMaterialMovements.stockItem.category'", $controller);
        $this->assertStringContainsString("'procedureMaterialMovements.maintenanceRecordItem.procedure'", $controller);
        $this->assertStringContainsString("'materials_total' => \$canViewCosts ? (float) \$maintenance->materialUsages->sum('total_cost') : null", $controller);
        $this->assertStringNotContainsString("procedureMaterialMovements->sum('total_cost')", $controller);
    }

    public function test_procedure_material_cost_respects_cost_permission(): void
    {
        $list = file_get_contents(resource_path('views/vehicle/partials/maintenance-materials-list.blade.php'));
        $procedureBlock = substr($list, strpos($list, '@foreach($maintenance->procedureMaterialMovements'));

        $this->assertStringContainsString('@if($canViewCosts)', $procedureBlock);
        $this->assertStringContainsString('Valor restrito', $procedureBlock);
    }

    private function assertInvalidMaterialQuantity(mixed $quantity): void
    {
        try {
            MaintenanceMaterialService::validatedQuantity($quantity);
            $this->fail('A quantidade inválida deveria ter sido rejeitada.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'Informe uma quantidade inteira igual ou maior que 1.',
                $exception->errors()['quantity'][0]
            );
        }
    }
}
