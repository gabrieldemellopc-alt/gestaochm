<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tires', function (Blueprint $table) {
            $table->foreignId('location_id')
                ->nullable()
                ->after('tenant_id')
                ->constrained('locations')
                ->nullOnDelete();

            $table->index(
                ['tenant_id', 'location_id', 'status'],
                'tires_tenant_location_status_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('tires', function (Blueprint $table) {
            $table->dropIndex('tires_tenant_location_status_index');
            $table->dropForeign(['location_id']);
            $table->dropColumn('location_id');
        });
    }
};
