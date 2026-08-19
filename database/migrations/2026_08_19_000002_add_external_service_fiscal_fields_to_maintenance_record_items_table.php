<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_record_items', function (Blueprint $table) {
            $table->string('provider_document', 20)->nullable()->after('provider_name');
            $table->string('fiscal_document_number', 255)->nullable()->after('provider_document');
            $table->date('fiscal_document_issued_at')->nullable()->after('fiscal_document_number');
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_record_items', function (Blueprint $table) {
            $table->dropColumn(['provider_document', 'fiscal_document_number', 'fiscal_document_issued_at']);
        });
    }
};
