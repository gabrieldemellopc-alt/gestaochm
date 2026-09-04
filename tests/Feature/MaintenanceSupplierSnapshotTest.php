<?php

namespace Tests\Feature;

use App\Models\Supplier;
use App\Services\SupplierResolverService;
use App\Services\SupplierSnapshotService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use RuntimeException;
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
        $resolved = app(SupplierResolverService::class)->resolve(10, $supplier->id, 'Nome do navegador', '999');
        $snapshot = app(SupplierSnapshotService::class)->maintenanceProvider($resolved, 'Nome do navegador');
        $this->assertSame($supplier->id, $snapshot['supplier_id']);
        $this->assertSame('Casa da Borracharia', $snapshot['supplier_name']);
        $this->assertSame('12345678000190', $snapshot['provider_document']);
    }

    public function test_selected_supplier_without_document_remains_authoritative(): void
    {
        $supplier = Supplier::create(['tenant_id'=>10, 'legal_name'=>'Oficina Manual', 'normalized_name'=>'oficina manual', 'active'=>true]);
        $resolved = app(SupplierResolverService::class)->resolve(10, $supplier->id, 'Ignorado', '52998224725');
        $snapshot = app(SupplierSnapshotService::class)->maintenanceProvider($resolved, 'Ignorado');
        $this->assertSame($supplier->id, $snapshot['supplier_id']);
        $this->assertSame('Oficina Manual', $snapshot['supplier_name']);
        $this->assertNull($snapshot['provider_document']);
    }

    public function test_manual_provider_creates_a_supplier_and_foreign_or_inactive_supplier_is_rejected(): void
    {
        $service = app(SupplierResolverService::class);
        $snapshotService = app(SupplierSnapshotService::class);
        $manual = $service->resolve(10, null, 'Prestador manual', '529.982.247-25');
        $this->assertSame(['supplier_id'=>$manual->id, 'supplier_name'=>'Prestador manual', 'provider_document'=>'52998224725'], $snapshotService->maintenanceProvider($manual));
        $foreign = Supplier::create(['tenant_id'=>20, 'legal_name'=>'Outro tenant', 'normalized_name'=>'outro tenant', 'active'=>true]);
        try { $service->resolve(10, $foreign->id, null, null); $this->fail('Fornecedor de outro tenant deveria ser rejeitado.'); } catch (ValidationException $e) { $this->assertArrayHasKey('supplier_id', $e->errors()); }
        $inactive = Supplier::create(['tenant_id'=>10, 'legal_name'=>'Inativo', 'normalized_name'=>'inativo', 'active'=>false]);
        try { $service->resolve(10, $inactive->id, null, null); $this->fail('Fornecedor inativo deveria ser rejeitado.'); } catch (ValidationException $e) { $this->assertArrayHasKey('supplier_id', $e->errors()); }
    }

    public function test_supplier_created_by_the_resolver_is_rolled_back_with_the_enclosing_operation(): void
    {
        try {
            DB::transaction(function () {
                app(SupplierResolverService::class)->resolve(10, null, 'Fornecedor transacional', '52998224725');
                throw new RuntimeException('Falha posterior simulada.');
            });
        } catch (RuntimeException $exception) {
            $this->assertSame('Falha posterior simulada.', $exception->getMessage());
        }

        $this->assertDatabaseMissing('suppliers', ['tenant_id' => 10, 'normalized_name' => 'fornecedor transacional']);
    }

    public function test_document_with_an_exact_name_candidate_requires_an_explicit_resolution(): void
    {
        $candidate = Supplier::create(['tenant_id'=>10, 'trade_name'=>'Oficina União', 'normalized_name'=>'oficina uniao', 'active'=>true]);

        try {
            app(SupplierResolverService::class)->resolve(10, null, 'Oficina União', '04.252.011/0001-10');
            $this->fail('A ambiguidade deveria exigir uma escolha explícita.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('supplier_resolution_action', $exception->errors());
            $this->assertSame((string) $candidate->id, $exception->errors()['supplier_candidate_id'][0]);
        }

        $this->assertDatabaseCount('suppliers', 1);
    }

    public function test_exact_name_candidate_can_be_enriched_or_kept_when_creating_a_new_supplier(): void
    {
        $candidate = Supplier::create(['tenant_id'=>10, 'trade_name'=>'Oficina União', 'normalized_name'=>'oficina uniao', 'active'=>true]);
        $service = app(SupplierResolverService::class);

        $enriched = $service->resolve(10, null, 'Oficina União', '04.252.011/0001-10', 'enrich_existing', $candidate->id);
        $this->assertSame($candidate->id, $enriched->id);
        $this->assertSame('04252011000110', $enriched->document);
        $this->assertSame('cnpj', $enriched->document_type);

        $new = $service->resolve(10, null, 'Oficina União', '529.982.247-25', 'create_new', $candidate->id);
        $this->assertNotSame($candidate->id, $new->id);
        $this->assertSame('52998224725', $new->document);
        $this->assertDatabaseHas('suppliers', ['id'=>$candidate->id, 'document'=>'04252011000110']);
    }

    public function test_enrichment_is_rolled_back_with_the_enclosing_operation(): void
    {
        $candidate = Supplier::create(['tenant_id'=>10, 'trade_name'=>'Oficina União', 'normalized_name'=>'oficina uniao', 'active'=>true]);

        try {
            DB::transaction(function () use ($candidate) {
                app(SupplierResolverService::class)->resolve(10, null, 'Oficina União', '04.252.011/0001-10', 'enrich_existing', $candidate->id);
                throw new RuntimeException('Falha posterior simulada.');
            });
        } catch (RuntimeException $exception) {
            $this->assertSame('Falha posterior simulada.', $exception->getMessage());
        }

        $this->assertDatabaseHas('suppliers', ['id'=>$candidate->id, 'document'=>null]);
    }

    public function test_document_owner_wins_over_name_candidate_and_candidate_is_tenant_scoped(): void
    {
        $owner = Supplier::create(['tenant_id'=>10, 'trade_name'=>'Fornecedor Documento', 'document'=>'04252011000110', 'document_type'=>'cnpj', 'normalized_name'=>'fornecedor documento', 'active'=>true]);
        $candidate = Supplier::create(['tenant_id'=>10, 'trade_name'=>'Oficina União', 'normalized_name'=>'oficina uniao', 'active'=>true]);
        $foreign = Supplier::create(['tenant_id'=>20, 'trade_name'=>'Oficina União', 'normalized_name'=>'oficina uniao', 'active'=>true]);
        $service = app(SupplierResolverService::class);

        try {
            $service->resolve(10, null, 'Oficina União', '04.252.011/0001-10', 'enrich_existing', $candidate->id);
            $this->fail('O documento já pertencente a outro fornecedor não pode enriquecer o candidato.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('supplier_resolution_action', $exception->errors());
        }

        try {
            $service->resolve(10, null, 'Oficina União', '529.982.247-25', 'enrich_existing', $foreign->id);
            $this->fail('Candidato de outro tenant deveria ser rejeitado.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('supplier_resolution_action', $exception->errors());
        }

        $this->assertSame('04252011000110', $owner->document);
        $this->assertDatabaseHas('suppliers', ['id'=>$candidate->id, 'document'=>null]);
    }

    public function test_enrichment_rejects_a_different_name_or_candidate_that_already_has_a_document(): void
    {
        $differentName = Supplier::create(['tenant_id'=>10, 'trade_name'=>'Outra Oficina', 'normalized_name'=>'outra oficina', 'active'=>true]);
        $withDocument = Supplier::create(['tenant_id'=>10, 'trade_name'=>'Oficina União', 'document'=>'04252011000110', 'document_type'=>'cnpj', 'normalized_name'=>'oficina uniao', 'active'=>true]);
        $service = app(SupplierResolverService::class);

        foreach ([
            ['candidate'=>$differentName, 'document'=>'529.982.247-25'],
            ['candidate'=>$withDocument, 'document'=>'123.456.789-09'],
        ] as $case) {
            try {
                $service->resolve(10, null, 'Oficina União', $case['document'], 'enrich_existing', $case['candidate']->id);
                $this->fail('Candidato incompatível deveria ser rejeitado.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('supplier_resolution_action', $exception->errors());
            }
        }
    }

    public function test_exact_name_without_a_document_still_reuses_the_existing_supplier(): void
    {
        $existing = Supplier::create(['tenant_id'=>10, 'trade_name'=>'Jr Silva', 'normalized_name'=>'jr silva', 'active'=>true]);
        $resolved = app(SupplierResolverService::class)->resolve(10, null, 'JR SILVA', '   ');

        $this->assertSame($existing->id, $resolved->id);
        $this->assertDatabaseCount('suppliers', 1);
    }

    public function test_phase_4a_resolves_suppliers_inside_the_existing_transactions(): void
    {
        $maintenanceService = file_get_contents(app_path('Services/MaintenanceService.php'));
        $materialService = file_get_contents(app_path('Services/MaintenanceMaterialService.php'));
        $controller = file_get_contents(app_path('Http/Controllers/MaintenanceController.php'));

        $create = strpos($maintenanceService, 'public static function create');
        $createTransaction = strpos($maintenanceService, 'return DB::transaction', $create);
        $createResolver = strpos($maintenanceService, 'SupplierResolverService::class', $create);
        $item = strpos($maintenanceService, 'public static function addItem');
        $itemTransaction = strpos($maintenanceService, 'return DB::transaction', $item);
        $itemResolver = strpos($maintenanceService, 'SupplierResolverService::class', $item);
        $direct = strpos($materialService, 'public function addDirectPurchase');
        $directTransaction = strpos($materialService, 'return DB::transaction', $direct);
        $directResolver = strpos($materialService, 'SupplierResolverService::class', $direct);

        $this->assertLessThan($createResolver, $createTransaction);
        $this->assertLessThan($itemResolver, $itemTransaction);
        $this->assertLessThan($directResolver, $directTransaction);
        $open = substr($controller, strpos($controller, 'public function store('), strpos($controller, 'public function cancel(') - strpos($controller, 'public function store('));
        $simpleItem = substr($controller, strpos($controller, 'public function storeItem('), strpos($controller, 'public function updateItem(') - strpos($controller, 'public function storeItem('));
        $directPurchase = substr($controller, strpos($controller, 'public function storeDirectMaterial('), strpos($controller, 'public function cancelMaterial(') - strpos($controller, 'public function storeDirectMaterial('));

        $this->assertStringNotContainsString('SupplierSnapshotService::class', $open);
        $this->assertStringNotContainsString('SupplierSnapshotService::class', $simpleItem);
        $this->assertStringNotContainsString('SupplierSnapshotService::class', $directPurchase);
        $this->assertStringContainsString("'supplier_document' => ['nullable', 'string', 'max:20']", $controller);
    }
}
