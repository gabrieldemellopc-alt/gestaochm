<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tires', function (Blueprint $table) {
            if (! Schema::hasColumn('tires', 'size')) {
                $table
                    ->string('size')
                    ->nullable()
                    ->after('model');
            }

            if (! Schema::hasColumn('tires', 'unit_cost')) {
                $table
                    ->decimal('unit_cost', 12, 2)
                    ->nullable()
                    ->after('purchase_date');
            }

            if (! Schema::hasColumn('tires', 'entry_id')) {
                $table
                    ->unsignedBigInteger('entry_id')
                    ->nullable()
                    ->after('tenant_id');

                $table
                    ->index('entry_id', 'tires_entry_id_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tires', function (Blueprint $table) {
            if (Schema::hasColumn('tires', 'entry_id')) {
                $table->dropIndex('tires_entry_id_index');
                $table->dropColumn('entry_id');
            }

            if (Schema::hasColumn('tires', 'unit_cost')) {
                $table->dropColumn('unit_cost');
            }

            if (Schema::hasColumn('tires', 'size')) {
                $table->dropColumn('size');
            }
        });
    }
};