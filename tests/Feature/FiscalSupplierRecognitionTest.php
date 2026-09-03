<?php

namespace Tests\Feature;

use App\Models\Supplier;
use App\Services\FiscalDocuments\FiscalSupplierRecognitionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FiscalSupplierRecognitionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp(); Schema::dropIfExists('supplier_aliases'); Schema::dropIfExists('suppliers');
        Schema::create('suppliers', function (Blueprint $t) {$t->id();$t->unsignedBigInteger('tenant_id');$t->string('legal_name')->nullable();$t->string('trade_name')->nullable();$t->string('document',14)->nullable();$t->string('document_type',4)->nullable();$t->string('normalized_name');$t->boolean('active')->default(true);$t->timestamps();});
        Schema::create('supplier_aliases', function (Blueprint $t) {$t->id();$t->unsignedBigInteger('supplier_id');$t->string('alias');$t->string('normalized_alias');$t->timestamps();});
    }

    public function test_valid_cnpj_matches_the_tenant_supplier_without_changing_fiscal_name(): void
    {
        $supplier=Supplier::create(['tenant_id'=>1,'trade_name'=>'Casa da Borracharia','document'=>'11222333000181','document_type'=>'cnpj','normalized_name'=>'casa da borracharia','active'=>true]);
        $match=app(FiscalSupplierRecognitionService::class)->recognize(1,'Casa Borracharia Ltda','11.222.333/0001-81','xml');
        $this->assertTrue($match['valid_cnpj']); $this->assertSame($supplier->id,$match['supplier_id']); $this->assertSame('Casa da Borracharia',$match['supplier_name']);
    }

    public function test_unknown_or_invalid_document_does_not_create_or_auto_link_supplier(): void
    {
        $service=app(FiscalSupplierRecognitionService::class);
        $unknown=$service->recognize(1,'Fornecedor XML','12.345.678/0001-90','xml');
        $invalid=$service->recognize(1,'Fornecedor DANFE','12.345.678/0001-91','pdf');
        $this->assertNull($unknown['supplier_id']); $this->assertNull($invalid['supplier_id']); $this->assertFalse($invalid['valid_cnpj']); $this->assertDatabaseCount('suppliers',0);
    }

    public function test_explicit_creation_and_cross_tenant_protection_are_server_controlled(): void
    {
        $service=app(FiscalSupplierRecognitionService::class); $created=$service->createFromFiscal(1,'Fornecedor da NF-e','11.222.333/0001-81');
        $this->assertSame($created->id,$service->createFromFiscal(1,'Nome diferente','11.222.333/0001-81')->id);
        $foreign=Supplier::create(['tenant_id'=>2,'legal_name'=>'Outro tenant','normalized_name'=>'outro tenant','active'=>true]);
        $this->expectException(ValidationException::class); $service->resolve(1,$foreign->id);
    }
}
