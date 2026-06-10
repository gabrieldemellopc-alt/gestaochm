<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tire_entries', function (Blueprint $table) {
            $table->foreignId('location_id')
                ->nullable()
                ->after('tenant_id')
                ->constrained('locations')
                ->nullOnDelete();

            $table->index(
                ['tenant_id', 'location_id', 'entry_date'],
                'tire_entries_tenant_location_date_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('tire_entries', function (Blueprint $table) {
            $table->dropIndex('tire_entries_tenant_location_date_index');
            $table->dropForeign(['location_id']);
            $table->dropColumn('location_id');
        });
    }
};
