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

    public function test_picker_has_document_mask_validation_and_explicit_ambiguity_actions(): void
    {
        $component = file_get_contents(resource_path('views/components/supplier-autocomplete.blade.php'));

        $this->assertStringContainsString('CPF/CNPJ', $component);
        $this->assertStringContainsString('validateDocument(true)', $component);
        $this->assertStringContainsString('Informe um CPF ou CNPJ válido.', $component);
        $this->assertStringContainsString('supplier_resolution_action', $component);
        $this->assertStringContainsString('supplier_candidate_id', $component);
        $this->assertStringContainsString('Atualizar cadastro existente', $component);
        $this->assertStringContainsString('Cadastrar como novo', $component);
        $this->assertStringContainsString('Usar fornecedor cadastrado', $component);
        $this->assertStringContainsString('documentOwner', $component);
        $this->assertStringContainsString('isCompleteValidDocument', $component);
        $this->assertStringContainsString('scheduleSearch(name || value, 300)', $component);
        $this->assertStringContainsString('if (!this.isCompleteValidDocument(value)) return;', $component);
        $this->assertStringContainsString("state: 'idle'", $component);
        $this->assertStringContainsString("state === 'suggestion'", $component);
        $this->assertStringContainsString("state === 'resolution_chosen'", $component);
        $this->assertStringContainsString('this.$refs.id.value = \'\'; this.resolutionAction = \'enrich_existing\'', $component);
        $this->assertStringContainsString('this.$refs.id.value = \'\'; this.resolutionAction = \'create_new\'', $component);
        $this->assertStringContainsString('Trocar fornecedor', $component);
        $this->assertStringContainsString("enrich_existing", $component);
        $this->assertStringContainsString("create_new", $component);
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
        $css = file_get_contents(public_path('css/components/supplier-autocomplete.css'));
        $this->assertStringContainsString('chm-supplier-picker__fields--split', $component);
        $this->assertStringContainsString('.chm-supplier-picker__fields--split{grid-template-columns', $css);
        $this->assertStringContainsString('.chm-supplier-picker__status{grid-column:1/-1', $css);
        $this->assertStringContainsString('.chm-supplier-picker__resolution', $css);
        $this->assertStringContainsString('.chm-supplier-picker__resolution button.is-selected', $css);
        $this->assertStringContainsString('supplier-autocomplete.css', file_get_contents(resource_path('views/layouts/app.blade.php')));
    }

    public function test_supplier_edit_modal_reuses_the_form_and_status_toggle(): void
    {
        $view = file_get_contents(resource_path('views/suppliers/index.blade.php'));
        $controller = file_get_contents(app_path('Http/Controllers/SupplierController.php'));

        $this->assertStringContainsString('openEdit(', $view);
        $this->assertStringContainsString('name="aliases_text"', $view);
        $this->assertStringContainsString("typeof a==='string'?a:a?.alias", $view);
        $this->assertStringContainsString('suppliers-status-toggle', $view);
        $this->assertStringContainsString('Desativar este fornecedor?', $view);
        $this->assertStringContainsString('window.supplierRecords', $view);
        $this->assertStringContainsString('openEdit(suppliers[', $view);
        $this->assertStringContainsString('toggle(suppliers[', $view);
        $this->assertStringContainsString('x-ref="statusForm"', $view);
        $this->assertStringContainsString("@method('PATCH')", $view);
        $this->assertStringContainsString('submitStatus(s)', $view);
        $this->assertStringNotContainsString('name="aliases[]" x-model="modal.aliasesText"', substr($view, strpos($view, 'x-ref="statusForm"')));
        $this->assertStringContainsString('type="button" @click="openEdit', $view);
        $this->assertStringContainsString('public function updateStatus', $controller);
        $this->assertStringContainsString("'active' => ['required', 'boolean']", $controller);
        $this->assertStringContainsString("whereNotIn('normalized_alias'", $controller);
    }
}
