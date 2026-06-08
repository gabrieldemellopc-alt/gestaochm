<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | DAILY CHECKLISTS
        |--------------------------------------------------------------------------
        */

        Schema::create('daily_checklists', function (Blueprint $table) {
            $table->id();

            $table
                ->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            $table
                ->foreignId('division_id')
                ->constrained('divisions')
                ->cascadeOnDelete();

            $table
                ->foreignId('location_id')
                ->nullable()
                ->constrained('locations')
                ->nullOnDelete();

            $table
                ->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table
                ->foreignId('vehicle_id')
                ->nullable()
                ->constrained('vehicles')
                ->nullOnDelete();

            $table
                ->string('module')
                ->default('fleet');

            $table
                ->string('profile');

            $table
                ->date('checklist_date');

            $table
                ->string('status')
                ->default('draft');

            $table
                ->text('notes')
                ->nullable();

            $table
                ->timestamp('completed_at')
                ->nullable();

            $table->timestamps();

            $table->index([
                'tenant_id',
                'division_id',
                'location_id',
                'user_id',
                'checklist_date',
            ], 'daily_checklists_context_index');
        });

        /*
        |--------------------------------------------------------------------------
        | DAILY CHECKLIST ITEMS
        |--------------------------------------------------------------------------
        */

        Schema::create('daily_checklist_items', function (Blueprint $table) {
            $table->id();

            $table
                ->foreignId('daily_checklist_id')
                ->constrained('daily_checklists')
                ->cascadeOnDelete();

            $table
                ->string('key');

            $table
                ->string('label');

            $table
                ->boolean('checked')
                ->default(false);

            $table
                ->text('notes')
                ->nullable();

            $table->timestamps();

            $table->unique([
                'daily_checklist_id',
                'key',
            ], 'daily_checklist_items_unique_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_checklist_items');

        Schema::dropIfExists('daily_checklists');
    }
};