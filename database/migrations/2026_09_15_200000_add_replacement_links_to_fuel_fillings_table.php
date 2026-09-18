<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fuel_fillings', function (Blueprint $table) {
            $table->foreignId('replaces_filling_id')
                ->nullable()
                ->after('cancel_reason')
                ->constrained('fuel_fillings')
                ->nullOnDelete();

            $table->foreignId('replaced_by_filling_id')
                ->nullable()
                ->after('replaces_filling_id')
                ->constrained('fuel_fillings')
                ->nullOnDelete();

            $table->index('replaces_filling_id');
            $table->index('replaced_by_filling_id');
        });
    }

    public function down(): void
    {
        Schema::table('fuel_fillings', function (Blueprint $table) {
            $table->dropForeign(['replaces_filling_id']);
            $table->dropForeign(['replaced_by_filling_id']);

            $table->dropIndex(['replaces_filling_id']);
            $table->dropIndex(['replaced_by_filling_id']);

            $table->dropColumn([
                'replaces_filling_id',
                'replaced_by_filling_id',
            ]);
        });
    }
};
