<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;

class MaintenanceCancelledSheet implements FromArray, WithTitle
{
    public function __construct(
        protected array $data
    ) {
    }

    public function array(): array
    {
        $rows = [[
            'Cancelada em',
            'Veículo',
            'Placa',
            'Procedimento',
            'Cancelada por',
            'Motivo',
        ]];

        foreach ($this->data['cancelledMaintenances'] ?? [] as $maintenance) {
            $rows[] = [
                optional($maintenance['date'])->format('d/m/Y H:i'),
                $maintenance['vehicle'],
                $maintenance['plate'],
                $maintenance['procedure'],
                $maintenance['cancelled_by'],
                $maintenance['reason'],
            ];
        }

        return $rows;
    }

    public function title(): string
    {
        return 'Canceladas';
    }
}
