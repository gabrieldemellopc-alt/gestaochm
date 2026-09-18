<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fuel_receipts', function (Blueprint $table) {
            $table->date('invoice_date')
                ->nullable()
                ->after('invoice_number');

            $table->boolean('invoice_pending')
                ->default(false)
                ->after('invoice_date');

            $table->foreignId('replaces_receipt_id')
                ->nullable()
                ->after('invoice_pending')
                ->constrained('fuel_receipts')
                ->nullOnDelete();

            $table->foreignId('replaced_by_receipt_id')
                ->nullable()
                ->after('replaces_receipt_id')
                ->constrained('fuel_receipts')
                ->nullOnDelete();

            $table->index(
                ['tenant_id', 'invoice_number'],
                'fuel_receipts_invoice_lookup_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('fuel_receipts', function (Blueprint $table) {
            $table->dropIndex('fuel_receipts_invoice_lookup_index');

            $table->dropConstrainedForeignId('replaced_by_receipt_id');
            $table->dropConstrainedForeignId('replaces_receipt_id');

            $table->dropColumn([
                'invoice_pending',
                'invoice_date',
            ]);
        });
    }
};
