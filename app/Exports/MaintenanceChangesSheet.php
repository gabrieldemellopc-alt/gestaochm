<?php

namespace App\Exports;

use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;

class MaintenanceChangesSheet implements FromArray, ShouldAutoSize, WithTitle
{
    public function __construct(protected array $data)
    {
    }

    public function array(): array
    {
        $rows = [[
            'Ordem', 'Veiculo', 'Placa', 'Alterado em', 'Responsavel',
            'Servico anterior', 'Novo servico', 'Motivo', 'Estoque devolvido',
            'Novo consumo', 'Custo anterior', 'Novo custo', 'Considerado nos totais?',
        ]];

        foreach ($this->data['maintenances'] ?? [] as $maintenance) {
            foreach ($maintenance['changes'] ?? [] as $change) {
                $rows[] = [
                    '#'.($maintenance['id'] ?? '-'),
                    $maintenance['vehicle_name'] ?? '-',
                    $maintenance['vehicle_plate'] ?? '-',
                    ! empty($change['changed_at'])
                        ? Carbon::parse($change['changed_at'])->format('d/m/Y H:i')
                        : '-',
                    $change['changed_by'] ?? '-',
                    $change['old_procedure'] ?? '-',
                    $change['replacement_procedure'] ?? '-',
                    $change['reason'] ?? '-',
                    $this->stockSummary($change['returned_stock'] ?? []),
                    $this->stockSummary($change['new_stock'] ?? []),
                    ! empty($this->data['canViewCosts']) ? $change['old_cost'] : 'Restrito',
                    ! empty($this->data['canViewCosts']) ? $change['replacement_cost'] : 'Restrito',
                    'Nao',
                ];
            }
        }

        return $rows;
    }

    public function title(): string
    {
        return 'Alterações';
    }

    private function stockSummary(array $movements): string
    {
        return collect($movements)
            ->map(fn (array $movement) => ($movement['item'] ?? '-').' ('.($movement['quantity'] ?? 0).')')
            ->implode('; ') ?: '-';
    }
}
