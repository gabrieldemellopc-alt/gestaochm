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
        Schema::table('maintenance_record_items', function (Blueprint $table) {
            $table->integer('next_due_km')->nullable()->after('performed_at');
            $table->integer('next_due_hours')->nullable()->after('next_due_km');
            $table->date('next_due_date')->nullable()->after('next_due_hours');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('maintenance_record_items', function (Blueprint $table) {
            $table->dropColumn([
                'next_due_km',
                'next_due_hours',
                'next_due_date',
            ]);
        });
    }
};
