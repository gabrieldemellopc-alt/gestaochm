<?php

namespace Database\Seeders;

use App\Models\FuelProduct;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class FuelProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            [
                'name' => 'Diesel',
                'slug' => 'diesel',
                'unit' => 'litros',
            ],
            ['name' => 'Diesel S10', 'slug' => 'diesel-s10', 'unit' => 'litros'],
            ['name' => 'Diesel S500', 'slug' => 'diesel-s500', 'unit' => 'litros'],
            ['name' => 'Álcool', 'slug' => 'alcool', 'unit' => 'litros'],
            ['name' => 'Gasolina', 'slug' => 'gasolina', 'unit' => 'litros'],
            [
                'name' => 'ARLA',
                'slug' => 'arla',
                'unit' => 'litros',
            ],
        ];

        Tenant::query()
            ->orderBy('id')
            ->get(['id'])
            ->each(function (Tenant $tenant) use ($products) {
                foreach ($products as $product) {
                    FuelProduct::query()->updateOrCreate(
                        [
                            'tenant_id' => $tenant->id,
                            'slug' => $product['slug'],
                        ],
                        [
                            'name' => $product['name'],
                            'unit' => $product['unit'],
                            'active' => true,
                        ]
                    );
                }
            });
    }
}
