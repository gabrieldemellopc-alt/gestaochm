<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('fuel_product_vehicle', function (Blueprint $table) {
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fuel_product_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['vehicle_id', 'fuel_product_id']);
        });

        foreach (DB::table('tenants')->pluck('id') as $tenantId) {
            foreach (['diesel-s10' => 'Diesel S10', 'diesel-s500' => 'Diesel S500', 'alcool' => 'Álcool', 'gasolina' => 'Gasolina'] as $slug => $name) {
                $existing = DB::table('fuel_products')->where('tenant_id', $tenantId)->where('slug', $slug)->first()
                    ?? DB::table('fuel_products')->where('tenant_id', $tenantId)->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();
                if ($existing) { DB::table('fuel_products')->where('id', $existing->id)->update(['slug' => $slug, 'name' => $name, 'active' => true, 'updated_at' => now()]); }
                else { DB::table('fuel_products')->insert(['tenant_id' => $tenantId, 'name' => $name, 'slug' => $slug, 'unit' => 'litros', 'active' => true, 'created_at' => now(), 'updated_at' => now()]); }
            }
        }
    }
    public function down(): void { Schema::dropIfExists('fuel_product_vehicle'); }
};
