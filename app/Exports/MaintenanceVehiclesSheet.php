<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithTitle;

class MaintenanceVehiclesSheet implements FromCollection, WithTitle
{
    protected $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function collection()
    {
        $rows = collect();

        $rows->push([
            'Veículo',
            'Placa',
            'KM rodados',
            'HR rodadas',
            'Custo registrado das ordens'
        ]);

        foreach ($this->data['vehicleCosts'] as $vehicle)
        {
            $rows->push([

                $vehicle['vehicle'],

                $vehicle['plate'],

                $vehicle['km_driven'],

                $vehicle['hours_driven'],

                $vehicle['total']

            ]);
        }

        return $rows;
    }

    public function title(): string
    {
        return 'Custos por veículo';
    }
}
