<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_checklists', function (Blueprint $table) {
            if (! Schema::hasColumn('daily_checklists', 'checklist_template_id')) {
                $table
                    ->unsignedBigInteger('checklist_template_id')
                    ->nullable()
                    ->after('vehicle_id');

                $table
                    ->index('checklist_template_id', 'daily_checklists_template_id_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('daily_checklists', function (Blueprint $table) {
            if (Schema::hasColumn('daily_checklists', 'checklist_template_id')) {
                $table->dropIndex('daily_checklists_template_id_index');

                $table->dropColumn('checklist_template_id');
            }
        });
    }
};