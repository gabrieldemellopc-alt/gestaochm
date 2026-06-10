<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procedures', function (Blueprint $table) {
            $table->foreignId('location_id')
                ->nullable()
                ->after('tenant_id')
                ->constrained('locations')
                ->nullOnDelete();

            $table->index(
                ['tenant_id', 'location_id'],
                'procedures_tenant_location_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('procedures', function (Blueprint $table) {
            $table->dropIndex('procedures_tenant_location_index');
            $table->dropForeign(['location_id']);
            $table->dropColumn('location_id');
        });
    }
};
