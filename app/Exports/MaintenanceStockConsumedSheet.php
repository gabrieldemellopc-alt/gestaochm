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
            'Manutenção',
            'Veículo',
            'Placa',
            'Item',
            'Quantidade',
            'Custo unitário',
            'Total',
        ]];

        foreach ($this->data['stockConsumed'] ?? [] as $movement) {
            $rows[] = [
                optional($movement['date'])->format('d/m/Y H:i'),
                $movement['maintenance_id'],
                $movement['vehicle'],
                $movement['plate'],
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
