<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;

class MaintenanceProceduresSheet implements FromArray, WithTitle
{
    protected $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function array(): array
    {
        $rows = [];

        $rows[] = [
            'Procedimento',
            'Quantidade',
            'Custo por item/procedimento',
            'Média'
        ];

        foreach ($this->data['procedureStats'] as $stat) {

            $rows[] = [

                $stat['procedure'],
                $stat['count'],
                $stat['total'],
                $stat['average']

            ];
        }

        return $rows;
    }

    public function title(): string
    {
        return 'Procedimentos';
    }
}
