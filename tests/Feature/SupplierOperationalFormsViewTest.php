<?php

namespace Tests\Feature;

use Tests\TestCase;

class SupplierOperationalFormsViewTest extends TestCase
{
    public function test_operational_supplier_forms_send_the_supported_document_field(): void
    {
        $views = [
            resource_path('views/stock/index.blade.php') => 'document-name="supplier_document"',
            resource_path('views/workshop/partials/financial.blade.php') => 'document-name="supplier_document"',
            resource_path('views/fuel/tanks/index.blade.php') => 'document-name="supplier_document"',
            resource_path('views/workshop/tires/index.blade.php') => 'document-name="supplier_document"',
            resource_path('views/vehicle/maintenance-create.blade.php') => 'document-name="supplier_document"',
            resource_path('views/vehicle/maintenance-add-item.blade.php') => 'document-name="provider_document"',
            resource_path('views/vehicle/partials/maintenance-edit-modals.blade.php') => 'document-name="provider_document"',
            resource_path('views/vehicle/partials/maintenance-materials-summary.blade.php') => 'document-name="supplier_document"',
        ];

        foreach ($views as $path => $documentField) {
            $this->assertStringContainsString($documentField, file_get_contents($path), $path);
        }
    }

    public function test_picker_has_document_mask_validation_and_clear_manual_state(): void
    {
        $component = file_get_contents(resource_path('views/components/supplier-autocomplete.blade.php'));

        $this->assertStringContainsString('CPF/CNPJ', $component);
        $this->assertStringContainsString('validateDocument(true)', $component);
        $this->assertStringContainsString('Informe um CPF ou CNPJ válido.', $component);
        $this->assertStringContainsString('this.clearSelection()', $component);
        $this->assertStringContainsString('Será cadastrado automaticamente ao confirmar este lançamento.', $component);
        $this->assertStringContainsString('this.search(d)', $component);
    }

    public function test_workshop_modals_use_theme_tokens_for_surfaces_and_inputs(): void
    {
        $css = file_get_contents(public_path('css/pages/workshop-financial.css'));

        foreach (['--chm-theme-card', '--chm-theme-card-elevated', '--chm-theme-input', '--chm-theme-border', '--chm-theme-text', '--chm-theme-muted'] as $token) {
            $this->assertStringContainsString($token, $css);
        }
    }

    public function test_workshop_expense_uses_the_split_supplier_grid(): void
    {
        $view = file_get_contents(resource_path('views/workshop/partials/financial.blade.php'));
        $component = file_get_contents(resource_path('views/components/supplier-autocomplete.blade.php'));

        $this->assertStringContainsString('chm-supplier-picker--split', $view);
        $this->assertStringContainsString('document-name="supplier_document"', $view);
        $this->assertLessThan(strpos($view, 'name="invoice_number"'), strpos($view, 'name="amount"'));
        $this->assertStringContainsString('.chm-supplier-picker--split{grid-template-columns', $component);
    }

    public function test_supplier_edit_modal_reuses_the_form_and_status_toggle(): void
    {
        $view = file_get_contents(resource_path('views/suppliers/index.blade.php'));
        $controller = file_get_contents(app_path('Http/Controllers/SupplierController.php'));

        $this->assertStringContainsString('openEdit(', $view);
        $this->assertStringContainsString('aliasesText:(s.aliases||[]).join', $view);
        $this->assertStringContainsString('suppliers-status-toggle', $view);
        $this->assertStringContainsString('Desativar este fornecedor?', $view);
        $this->assertStringContainsString('window.supplierRecords', $view);
        $this->assertStringContainsString('openEdit(suppliers[', $view);
        $this->assertStringContainsString('toggle(suppliers[', $view);
        $this->assertStringContainsString('form.requestSubmit()', $view);
        $this->assertStringContainsString('type="button" @click="openEdit', $view);
        $this->assertStringContainsString("whereNotIn('normalized_alias'", $controller);
    }
}
