<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procedures', function (Blueprint $table) {
            $table->dropForeign(['location_id']);

            $table->foreignId('location_id')
                ->nullable(false)
                ->change();

            $table->foreign('location_id')
                ->references('id')
                ->on('locations')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('procedures', function (Blueprint $table) {
            $table->dropForeign(['location_id']);

            $table->foreignId('location_id')
                ->nullable()
                ->change();

            $table->foreign('location_id')
                ->references('id')
                ->on('locations')
                ->nullOnDelete();
        });
    }
};
