<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'fuel_daily_check_files',
            function (Blueprint $table) {
                $table->string('document_type', 40)
                    ->nullable()
                    ->after('source');

                $table->date('document_date')
                    ->nullable()
                    ->after('document_type');

                $table->string('invoice_number', 120)
                    ->nullable()
                    ->after('document_date');

                $table->index(
                    ['document_type', 'document_date'],
                    'fuel_daily_files_type_date_index'
                );

                $table->index(
                    'invoice_number',
                    'fuel_daily_files_invoice_number_index'
                );
            }
        );

        Schema::create(
            'fuel_daily_check_file_receipt',
            function (Blueprint $table) {
                $table->id();

                $table->foreignId('fuel_daily_check_file_id')
                    ->constrained('fuel_daily_check_files')
                    ->cascadeOnDelete();

                $table->foreignId('fuel_receipt_id')
                    ->constrained('fuel_receipts')
                    ->cascadeOnDelete();

                $table->timestamps();

                $table->unique(
                    [
                        'fuel_daily_check_file_id',
                        'fuel_receipt_id',
                    ],
                    'fuel_daily_file_receipt_unique'
                );

                $table->index(
                    'fuel_receipt_id',
                    'fuel_daily_file_receipt_receipt_index'
                );
            }
        );

        /*
         * Arquivos gerados pela Foto IA já possuem semântica
         * conhecida: são folhas de abastecimentos.
         *
         * Os demais arquivos antigos permanecem sem classificação
         * para não presumirmos a natureza documental deles.
         */
        DB::table('fuel_daily_check_files')
            ->where('source', 'photo_import')
            ->whereNull('document_type')
            ->update([
                'document_type' => 'fuel_sheet',
            ]);
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'fuel_daily_check_file_receipt'
        );

        Schema::table(
            'fuel_daily_check_files',
            function (Blueprint $table) {
                $table->dropIndex(
                    'fuel_daily_files_type_date_index'
                );

                $table->dropIndex(
                    'fuel_daily_files_invoice_number_index'
                );

                $table->dropColumn([
                    'document_type',
                    'document_date',
                    'invoice_number',
                ]);
            }
        );
    }
};
