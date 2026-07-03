<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;

class MaintenanceStockConsumedSheet implements FromArray, WithTitle
{
    public function __construct(
        protected array $data
    ) {
    }

    public function array(): array
    {
        $rows = [[
            'Data',
            'Manutencao',
            'Veiculo',
            'Placa',
            'Procedimento',
            'Item',
            'Quantidade',
            'Custo unitario',
            'Total',
        ]];

        foreach ($this->data['stockConsumed'] ?? [] as $movement) {
            $rows[] = [
                optional($movement['date'])->format('d/m/Y H:i'),
                $movement['maintenance_id'],
                $movement['vehicle'],
                $movement['plate'],
                $movement['procedure'] ?? '-',
                $movement['item'],
                $movement['quantity'],
                $movement['unit_cost'],
                $movement['total'],
            ];
        }

        return $rows;
    }

    public function title(): string
    {
        return 'Estoque consumido';
    }
}
