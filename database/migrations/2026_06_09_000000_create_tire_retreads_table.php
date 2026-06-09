<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tire_retreads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('tire_id')->constrained('tires')->cascadeOnDelete();
            $table->date('retreaded_at');
            $table->decimal('new_tread_depth', 5, 2);
            $table->decimal('previous_tread_reference', 5, 2)->nullable();
            $table->string('provider_name', 150);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(
                ['tenant_id', 'tire_id', 'retreaded_at'],
                'tire_retreads_tenant_tire_date_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tire_retreads');
    }
};
