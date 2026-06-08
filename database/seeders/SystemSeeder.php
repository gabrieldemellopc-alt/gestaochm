<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

use App\Models\User;
use App\Models\Tenant;
use App\Models\Division;
use App\Models\Location;
use App\Models\Vehicle;
use App\Models\Procedure;
use App\Models\ProcedureField;
use App\Models\StockItem;
use App\Models\VehicleAllocation;

class SystemSeeder extends Seeder
{
    public function run(): void
    {
        /*
        |--------------------------------------------------------------------------
        | TENANT
        |--------------------------------------------------------------------------
        */

        $tenant = Tenant::create([

            'name' => 'CHM'
        ]);

        /*
        |--------------------------------------------------------------------------
        | ADMIN
        |--------------------------------------------------------------------------
        */

        User::create([

            'name' => 'Administrador',

            'email' => 'admin@admin.com',

            'password' => bcrypt('12345678'),

            'tenant_id' => $tenant->id
        ]);

        /*
        |--------------------------------------------------------------------------
        | DIVISION
        |--------------------------------------------------------------------------
        */

        $division = Division::create([

            'tenant_id' => $tenant->id,

            'name' => 'AKSA'
        ]);

        /*
        |--------------------------------------------------------------------------
        | LOCATIONS
        |--------------------------------------------------------------------------
        */

        $barreiras = Location::create([

            'tenant_id' => $tenant->id,

            'division_id' => $division->id,

            'name' => 'Barreiras'
        ]);

        $luis = Location::create([

            'tenant_id' => $tenant->id,

            'division_id' => $division->id,

            'name' => 'Luís Eduardo'
        ]);

        /*
        |--------------------------------------------------------------------------
        | STOCK
        |--------------------------------------------------------------------------
        */

        $oil = StockItem::create([

            'tenant_id' => $tenant->id,

            'name' => 'Óleo Shell 15w40',

            'unit' => 'L',

            'quantity' => 500,

            'minimum_quantity' => 100,

            'unit_cost' => 32.90,

            'active' => true
        ]);

        /*
        |--------------------------------------------------------------------------
        | PROCEDURES
        |--------------------------------------------------------------------------
        */

        $procedure = Procedure::create([

            'tenant_id' => $tenant->id,

            'name' => 'Troca de óleo',

            'validity_type' => 'km',

            'interval_km' => 10000,

            'can_be_internal' => true
        ]);

        /*
        |--------------------------------------------------------------------------
        | PROCEDURE FIELDS
        |--------------------------------------------------------------------------
        */

        ProcedureField::create([

            'procedure_id' => $procedure->id,

            'label' => 'Óleo utilizado',

            'slug' => 'oil_stock',

            'field_type' => 'stock_item',

            'required' => true
        ]);

        ProcedureField::create([

            'procedure_id' => $procedure->id,

            'label' => 'Quantidade litros',

            'slug' => 'oil_quantity',

            'field_type' => 'number',

            'required' => true
        ]);

        ProcedureField::create([

            'procedure_id' => $procedure->id,

            'label' => 'Filtro trocado',

            'slug' => 'filter_changed',

            'field_type' => 'boolean',

            'required' => false
        ]);

        /*
        |--------------------------------------------------------------------------
        | VEHICLES
        |--------------------------------------------------------------------------
        */

        for ($i = 1; $i <= 12; $i++) {

            $vehicle = Vehicle::create([

                'tenant_id' => $tenant->id,

                'name' => 'Caminhão '.$i,

                'plate' => 'ABC-'.$i.'234',

                'brand' => 'Volvo',

                'model' => 'FH 540',

                'year' => '2022',

                'current_km' => rand(10000, 400000),

                'current_hours' => rand(100, 10000),

                'status' => 'active'
            ]);

            VehicleAllocation::create([

                'vehicle_id' => $vehicle->id,

                'division_id' => $division->id,

                'location_id' =>
                    rand(0,1)
                    ? $barreiras->id
                    : $luis->id,

                'started_at' => now(),

                'is_current' => true
            ]);
        }
    }
}