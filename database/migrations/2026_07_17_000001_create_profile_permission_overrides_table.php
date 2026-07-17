<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profile_permission_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('division_id')->nullable()->constrained('divisions')->cascadeOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('locations')->cascadeOnDelete();
            $table->string('module', 40)->default('fleet');
            $table->string('profile', 40);
            $table->string('permission_key', 120);
            $table->boolean('allowed')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique([
                'tenant_id',
                'division_id',
                'location_id',
                'module',
                'profile',
                'permission_key',
            ], 'profile_permission_scope_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_permission_overrides');
    }
};