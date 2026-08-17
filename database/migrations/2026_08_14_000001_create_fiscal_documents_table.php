<?php
use Illuminate\Database\Migrations\Migration; use Illuminate\Database\Schema\Blueprint; use Illuminate\Support\Facades\Schema;
return new class extends Migration { public function up(): void { Schema::create('fiscal_documents', function(Blueprint $t){
    $t->id(); $t->foreignId('tenant_id')->constrained()->cascadeOnDelete(); $t->foreignId('division_id')->nullable()->constrained()->nullOnDelete(); $t->foreignId('location_id')->constrained()->cascadeOnDelete(); $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
    $t->string('number',60); $t->string('series',30)->nullable(); $t->string('access_key',44)->nullable(); $t->dateTime('issued_at')->nullable(); $t->string('supplier_name')->nullable(); $t->string('supplier_cnpj',14)->nullable(); $t->decimal('products_total',15,2)->nullable(); $t->decimal('total_amount',15,2)->nullable(); $t->string('pdf_path'); $t->timestamps(); $t->unique(['tenant_id','access_key']); $t->index(['tenant_id','supplier_cnpj','number','series']); });
    Schema::table('stock_movements', fn(Blueprint $t) => $t->foreignId('fiscal_document_id')->nullable()->after('stock_item_id')->constrained()->nullOnDelete()); }
    public function down(): void { Schema::table('stock_movements', fn(Blueprint $t) => $t->dropConstrainedForeignId('fiscal_document_id')); Schema::dropIfExists('fiscal_documents'); } };
