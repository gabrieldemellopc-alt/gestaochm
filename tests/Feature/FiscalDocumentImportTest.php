<?php

namespace Tests\Feature;

use Tests\TestCase;

class FiscalDocumentImportTest extends TestCase
{
    public function test_import_routes_are_protected_and_named(): void
    {
        $parseStatus = $this->postJson(route('fiscal-documents.import.parse'))->getStatusCode();
        $confirmStatus = $this->postJson(route('fiscal-documents.import.confirm'))->getStatusCode();
        $this->assertContains($parseStatus, [401, 419]);
        $this->assertContains($confirmStatus, [401, 419]);
    }

    public function test_import_permission_is_part_of_the_catalog(): void
    {
        $permissions = collect(config('chm_permissions.groups'))->flatMap(fn ($group) => array_keys($group['permissions']));
        $this->assertTrue($permissions->contains('fiscal_documents.import'));
    }

    public function test_import_ui_requires_validation_before_confirmation(): void
    {
        $view = file_get_contents(resource_path('views/fiscal-documents/_import-modal.blade.php'));
        $this->assertStringContainsString("step === 'review'", $view);
        $this->assertStringContainsString('Confirmar e lançar no estoque', $view);
        $this->assertStringContainsString("value=\"ignore\"", $view);
        $this->assertStringContainsString("value=\"new\"", $view);
        $this->assertStringNotContainsString("if(!this.note.items.length)this.addItem()", $view);
        $this->assertStringContainsString('accept=".xml,.pdf', $view);
    }

    public function test_confirmation_uses_transaction_duplicate_guard_and_shared_entry_service(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/FiscalDocumentImportController.php'));
        $this->assertStringContainsString('DB::transaction', $controller);
        $this->assertStringContainsString('Esta nota fiscal já foi importada.', $controller);
        $this->assertStringContainsString('StockEntryService $entries', $controller);
        $this->assertStringContainsString("if(\$line['action']==='ignore') continue", $controller);
    }

    public function test_valid_nfe_xml_returns_header_items_and_access_key(): void
    {
        $data = app(\App\Services\FiscalDocuments\NfeXmlParser::class)->parse(base_path('tests/Fixtures/nfe-example.xml'));
        $this->assertSame('xml', $data['import_source']);
        $this->assertSame('164', $data['number']);
        $this->assertSame('1', $data['series']);
        $this->assertSame('29260840187670000183550010000001641900026784', $data['access_key']);
        $this->assertSame('Fornecedor Exemplo Ltda', $data['supplier_name']);
        $this->assertSame('CHM Destinatário', $data['recipient_name']);
        $this->assertCount(2, $data['items']);
        $this->assertSame('P001', $data['items'][0]['product_code']);
        $this->assertSame('7891234567890', $data['items'][0]['ean']);
        $this->assertNull($data['items'][1]['ean']);
        $this->assertSame(220.0, $data['total_amount']);
    }

    public function test_nfe_proc_wrapper_is_supported(): void
    {
        $data = app(\App\Services\FiscalDocuments\NfeXmlParser::class)->parse(base_path('tests/Fixtures/nfe-example.xml'));
        $this->assertCount(2, $data['items']);
        $this->assertSame('29260840187670000183550010000001641900026784', $data['access_key']);
    }

    public function test_invalid_xml_has_a_friendly_error(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('O arquivo XML é inválido ou está corrompido.');
        app(\App\Services\FiscalDocuments\NfeXmlParser::class)->parse(base_path('tests/Fixtures/nfe-invalid.xml'));
    }

    public function test_xml_without_items_has_a_friendly_error(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('O XML da NF-e não possui itens de produtos (det).');
        app(\App\Services\FiscalDocuments\NfeXmlParser::class)->parse(base_path('tests/Fixtures/nfe-without-items.xml'));
    }

    public function test_original_extension_is_preserved_for_xml_and_pdf(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/FiscalDocumentImportController.php'));
        $this->assertStringContainsString("\$draft['extension']", $controller);
        $this->assertStringContainsString("\$source==='xml'", $controller);
    }

    public function test_import_entry_point_is_on_stock_without_fiscal_duplication(): void
    {
        $stock = file_get_contents(resource_path('views/stock/index.blade.php'));
        $fiscal = file_get_contents(resource_path('views/fiscal-documents/index.blade.php'));

        $this->assertStringContainsString('Importar NF', $stock);
        $this->assertStringContainsString("@include('fiscal-documents._import-modal')", $stock);
        $this->assertStringContainsString('fiscal-document-import-stock.css', $stock);
        $this->assertStringNotContainsString('Importar NF', $fiscal);
        $this->assertStringNotContainsString("@include('fiscal-documents._import-modal')", $fiscal);
    }

    public function test_stock_button_requires_both_existing_permissions(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/StockController.php'));
        $this->assertStringContainsString("'import_invoice' => \$this->canStock('stock.entry') && \$this->canStock('fiscal_documents.import')", $controller);
    }
}

