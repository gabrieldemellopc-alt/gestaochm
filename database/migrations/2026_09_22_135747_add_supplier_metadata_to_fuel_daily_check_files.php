<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'fuel_daily_check_files',
            function (Blueprint $table) {
                $table->foreignId('supplier_id')
                    ->nullable()
                    ->after('invoice_number')
                    ->constrained('suppliers')
                    ->nullOnDelete();

                $table->string('supplier_name', 255)
                    ->nullable()
                    ->after('supplier_id');

                $table->string('supplier_document', 20)
                    ->nullable()
                    ->after('supplier_name');
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'fuel_daily_check_files',
            function (Blueprint $table) {
                $table->dropConstrainedForeignId(
                    'supplier_id'
                );

                $table->dropColumn([
                    'supplier_name',
                    'supplier_document',
                ]);
            }
        );
    }
};
