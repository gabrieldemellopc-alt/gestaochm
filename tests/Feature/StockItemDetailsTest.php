<?php

namespace Tests\Feature;

use Tests\TestCase;

class StockItemDetailsTest extends TestCase
{
    public function test_stock_item_detail_routes_are_registered(): void
    {
        $this->assertNotNull(app('router')->getRoutes()->getByName('stock.items.show'));
        $this->assertNotNull(app('router')->getRoutes()->getByName('stock.items.data'));
        $this->assertNotNull(app('router')->getRoutes()->getByName('stock.items.report.pdf'));
    }

    public function test_pdf_report_requires_detail_permission_and_valid_period(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/StockController.php'));

        $this->assertStringContainsString('public function itemReportPdf(', $controller);
        $this->assertStringContainsString("\$this->authorizeStockPermission('stock.view_item_details')", $controller);
        $this->assertStringContainsString("'start_date' => ['required', 'date']", $controller);
        $this->assertStringContainsString("'end_date' => ['required', 'date', 'after_or_equal:start_date']", $controller);
        $this->assertStringContainsString("\$this->canStock('stock.view_costs')", $controller);
    }

    public function test_pdf_payload_filters_item_movements_and_hides_restricted_costs(): void
    {
        $service = file_get_contents(app_path('Services/StockItemDetailService.php'));
        $view = file_get_contents(resource_path('views/stock/pdf/item-report.blade.php'));

        $this->assertStringContainsString('buildPdfReportPayload', $service);
        $this->assertStringContainsString("->whereBetween('moved_at', [\$start, \$end])", $service);
        $this->assertStringContainsString("->where('stock_item_id', \$item->id)", $service);
        $this->assertStringContainsString("if (! \$canViewCosts)", $service);
        $this->assertStringContainsString('@if($canViewCosts)', $view);
        $this->assertStringContainsString('Movimentações no período', $view);
        $this->assertStringContainsString('Consumo em manutenções', $view);
    }

    public function test_detail_backend_scopes_data_and_protects_costs(): void
    {
        $service = file_get_contents(app_path('Services/StockItemDetailService.php'));
        $controller = file_get_contents(app_path('Http/Controllers/StockController.php'));

        $this->assertStringContainsString("->where('tenant_id', \$tenantId)", $service);
        $this->assertStringContainsString("->where('location_id', \$locationId)", $service);
        $this->assertStringContainsString("if (! \$canViewCosts)", $service);
        $this->assertStringContainsString("if (! \$canViewAudit)", $service);
        $this->assertStringContainsString("\$this->authorizeStockPermission('stock.view')", $controller);
        $this->assertStringContainsString("\$this->canStock('stock.view_costs')", $controller);
        $this->assertStringContainsString("\$this->authorizeStockPermission('stock.view_item_details')", $controller);
    }

    public function test_item_details_permission_defaults_to_managers_and_admins_only(): void
    {
        $permission = config('chm_permissions.groups.stock.permissions')['stock.view_item_details'];
        $service = file_get_contents(app_path('Services/Permissions/ProfilePermissionService.php'));

        $this->assertSame('Ver detalhamento dos itens do estoque', $permission['label']);
        $this->assertFalse($permission['default']['supervisor']);
        $this->assertStringContainsString("in_array(\$profile, ['admin', 'manager'], true)", $service);
        $this->assertStringContainsString('if ($override !== null)', $service);
    }

    public function test_stock_cards_reuse_existing_entry_modal_and_detail_page_has_requested_sections(): void
    {
        $index = file_get_contents(resource_path('views/stock/index.blade.php'));
        $show = file_get_contents(resource_path('views/stock/show.blade.php'));

        $this->assertStringContainsString('openDirectEntry', $index);
        $this->assertStringContainsString("openMovementModal('in')", $index);
        $this->assertStringContainsString('Ver mais detalhes', $index);
        $cardStart = strpos($index, 'stock-items-grid');
        $cardEnd = strpos($index, 'empty-stock', $cardStart);
        $cardSection = substr($index, $cardStart, $cardEnd - $cardStart);
        $this->assertStringNotContainsString('Ver mais detalhes', $cardSection);
        $this->assertStringContainsString('@if($canViewStockItemDetails)', $index);
        $this->assertStringContainsString('id="stockItemDetailsLink"', $index);
        $this->assertStringContainsString('Histórico de movimentações', $show);
        $this->assertStringContainsString('Evolução do custo unitário', $show);
        $this->assertStringContainsString('Consumo em manutenções', $show);
    }
}
