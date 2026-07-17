<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fuel_fillings', function (Blueprint $table) {
            if (! Schema::hasColumn('fuel_fillings', 'source')) {
                $table
                    ->string('source', 40)
                    ->default('internal_tank')
                    ->after('fuel_product_id');
            }

            if (! Schema::hasColumn('fuel_fillings', 'supplier_name')) {
                $table
                    ->string('supplier_name')
                    ->nullable()
                    ->after('total_cost');
            }

            if (! Schema::hasColumn('fuel_fillings', 'document_number')) {
                $table
                    ->string('document_number')
                    ->nullable()
                    ->after('supplier_name');
            }
        });

        $this->makeFuelTankNullable();
    }

    public function down(): void
    {
        Schema::table('fuel_fillings', function (Blueprint $table) {
            if (Schema::hasColumn('fuel_fillings', 'document_number')) {
                $table->dropColumn('document_number');
            }

            if (Schema::hasColumn('fuel_fillings', 'supplier_name')) {
                $table->dropColumn('supplier_name');
            }

            if (Schema::hasColumn('fuel_fillings', 'source')) {
                $table->dropColumn('source');
            }
        });

        $this->makeFuelTankRequired();
    }

    private function makeFuelTankNullable(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE fuel_fillings MODIFY fuel_tank_id BIGINT UNSIGNED NULL');

            return;
        }

        Schema::table('fuel_fillings', function (Blueprint $table) {
            $table->foreignId('fuel_tank_id')->nullable()->change();
        });
    }

    private function makeFuelTankRequired(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE fuel_fillings MODIFY fuel_tank_id BIGINT UNSIGNED NOT NULL');

            return;
        }

        Schema::table('fuel_fillings', function (Blueprint $table) {
            $table->foreignId('fuel_tank_id')->nullable(false)->change();
        });
    }
};
