<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // A migration anterior (2026_05_16_172704) is the canonical origin
        // of these columns. This historical duplicate must remain safe for
        // clean databases and for installations where it was already applied.
        if (! Schema::hasColumn('vehicles', 'last_km_update_at')) {
            Schema::table('vehicles', function (Blueprint $table) {
                $table->timestamp('last_km_update_at')->nullable();
            });
        }

        if (! Schema::hasColumn('vehicles', 'last_hours_update_at')) {
            Schema::table('vehicles', function (Blueprint $table) {
                $table->timestamp('last_hours_update_at')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Intentionally a no-op: this migration may have run after the
        // canonical one and must never remove columns it did not originate.
    }
};
