<?php

namespace Tests\Feature;

use App\Models\Supplier;
use App\Services\SupplierSnapshotService;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MaintenanceSupplierSnapshotTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::dropIfExists('suppliers');
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('tenant_id');
            $table->string('legal_name')->nullable(); $table->string('trade_name')->nullable();
            $table->string('document', 14)->nullable(); $table->string('document_type', 4)->nullable();
            $table->string('normalized_name'); $table->boolean('active')->default(true); $table->timestamps();
        });
    }

    public function test_selected_supplier_is_the_authoritative_maintenance_snapshot(): void
    {
        $supplier = Supplier::create(['tenant_id'=>10, 'trade_name'=>'Casa da Borracharia', 'document'=>'12345678000190', 'document_type'=>'cnpj', 'normalized_name'=>'casa da borracharia', 'active'=>true]);
        $snapshot = app(SupplierSnapshotService::class)->resolveMaintenanceProvider(10, $supplier->id, 'Nome do navegador', '999');
        $this->assertSame($supplier->id, $snapshot['supplier_id']);
        $this->assertSame('Casa da Borracharia', $snapshot['supplier_name']);
        $this->assertSame('12345678000190', $snapshot['provider_document']);
    }

    public function test_manual_provider_document_is_kept_when_selected_supplier_has_none(): void
    {
        $supplier = Supplier::create(['tenant_id'=>10, 'legal_name'=>'Oficina Manual', 'normalized_name'=>'oficina manual', 'active'=>true]);
        $snapshot = app(SupplierSnapshotService::class)->resolveMaintenanceProvider(10, $supplier->id, 'Ignorado', '52998224725');
        $this->assertSame($supplier->id, $snapshot['supplier_id']);
        $this->assertSame('Oficina Manual', $snapshot['supplier_name']);
        $this->assertSame('52998224725', $snapshot['provider_document']);
    }

    public function test_manual_provider_remains_supported_and_foreign_or_inactive_supplier_is_rejected(): void
    {
        $service = app(SupplierSnapshotService::class);
        $this->assertSame(['supplier_id'=>null, 'supplier_name'=>'Prestador manual', 'provider_document'=>'123'], $service->resolveMaintenanceProvider(10, null, 'Prestador manual', '123'));
        $foreign = Supplier::create(['tenant_id'=>20, 'legal_name'=>'Outro tenant', 'normalized_name'=>'outro tenant', 'active'=>true]);
        try { $service->resolveMaintenanceProvider(10, $foreign->id, null, null); $this->fail('Fornecedor de outro tenant deveria ser rejeitado.'); } catch (ValidationException $e) { $this->assertArrayHasKey('supplier_id', $e->errors()); }
        $inactive = Supplier::create(['tenant_id'=>10, 'legal_name'=>'Inativo', 'normalized_name'=>'inativo', 'active'=>false]);
        try { $service->resolveMaintenanceProvider(10, $inactive->id, null, null); $this->fail('Fornecedor inativo deveria ser rejeitado.'); } catch (ValidationException $e) { $this->assertArrayHasKey('supplier_id', $e->errors()); }
    }
}
