<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_material_usages', function (Blueprint $table) {
            $table->foreignId('maintenance_record_item_id')
                ->nullable()
                ->after('maintenance_record_id')
                ->constrained('maintenance_record_items')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_material_usages', function (Blueprint $table) {
            $table->dropForeign(['maintenance_record_item_id']);
            $table->dropColumn('maintenance_record_item_id');
        });
    }
};
