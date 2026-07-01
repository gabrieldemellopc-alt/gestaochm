<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->text('status_reason')
                ->nullable()
                ->after('operational_status');

            $table->timestamp('status_changed_at')
                ->nullable()
                ->after('status_reason');
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn([
                'status_reason',
                'status_changed_at',
            ]);
        });
    }
};