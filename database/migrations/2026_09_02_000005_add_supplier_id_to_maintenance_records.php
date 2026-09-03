<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('maintenance_records', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable()->after('provider_name')
                ->constrained('suppliers')->nullOnDelete();
        });

        Schema::table('maintenance_record_items', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable()->after('provider_name')
                ->constrained('suppliers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_record_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplier_id');
        });

        Schema::table('maintenance_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplier_id');
        });
    }
};
