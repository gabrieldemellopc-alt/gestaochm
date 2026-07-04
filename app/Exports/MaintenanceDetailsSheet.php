<?php

namespace App\Exports;

use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;

class MaintenanceDetailsSheet implements FromArray, ShouldAutoSize, WithTitle
{
    public function __construct(
        protected array $data
    ) {
    }

    public function array(): array
    {
        $rows = [[
            'Data',
            'Veiculo',
            'Placa',
            'Procedimentos',
            'Tipo',
            'Itens',
            'Custo registrado da ordem',
        ]];

        foreach ($this->data['maintenances'] ?? [] as $maintenance) {
            $rows[] = [
                $this->date($maintenance['date'] ?? $maintenance['created_at'] ?? $maintenance['started_at'] ?? null),
                $maintenance['vehicle_name'] ?? '-',
                $maintenance['vehicle_plate'] ?? '-',
                $maintenance['procedure_summary'] ?? $maintenance['procedure_name'] ?? '-',
                $this->maintenanceTypeLabel($maintenance['maintenance_type_summary'] ?? $maintenance['maintenance_type'] ?? null),
                $maintenance['items_count'] ?? 1,
                (float) ($maintenance['total_cost'] ?? 0),
            ];
        }

        return $rows;
    }

    public function title(): string
    {
        return 'Detalhado';
    }

    private function date(mixed $date): string
    {
        return $date ? Carbon::parse($date)->format('d/m/Y') : '-';
    }

    private function maintenanceTypeLabel(?string $type): string
    {
        return match ($type) {
            'internal' => 'Interna',
            'external' => 'Terceirizada',
            'mixed' => 'Mista',
            default => '-',
        };
    }
}
