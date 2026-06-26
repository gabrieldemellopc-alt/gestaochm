<?php

namespace App\Exports;

use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class TireReportExport implements WithMultipleSheets
{
    public function __construct(
        private readonly array $data
    ) {
    }

    public function sheets(): array
    {
        $sheets = [
            new TireReportArraySheet('Resumo', $this->summaryRows()),
            new TireReportArraySheet('Inventario', $this->inventoryRows()),
            new TireReportArraySheet('Pneus por veiculo', $this->vehicleRows()),
            new TireReportArraySheet('Eventos', $this->eventRows()),
            new TireReportArraySheet('Alertas', $this->alertRows()),
        ];

        if (
            ! empty($this->data['context']['can_view_cancelled'])
            && ! empty($this->data['cancelledRecords'])
            && $this->data['cancelledRecords']->count() > 0
        ) {
            $sheets[] = new TireReportArraySheet('Cancelados', $this->cancelledRows());
        }

        return $sheets;
    }

    private function summaryRows(): array
    {
        $summary = $this->data['summary'];
        $retreads = $this->data['retreadSummary'];
        $events = $this->data['eventSummary'];
        $filters = $this->data['filters'];

        return [
            ['Relatorio de Pneus'],
            ['Unidade', $this->data['context']['location']->name ?? '-'],
            ['Divisao', $this->data['context']['division']->name ?? '-'],
            ['Periodo', $this->date($filters['start_date']) . ' a ' . $this->date($filters['end_date'])],
            ['Periodo valido?', $filters['period_is_valid'] ? 'Sim' : 'Nao'],
            [],
            ['Inventario atual', 'Quantidade'],
            ['Total', $summary['total']],
            ['Instalados', $summary['installed']],
            ['Disponiveis', $summary['available']],
            ['Manutencao', $summary['maintenance']],
            ['Descartados', $summary['discarded']],
            ['Sulco critico', $summary['critical']],
            ['Sem medicao recente', $summary['no_recent_measurement']],
            [],
            ['Recapagens', 'Quantidade'],
            ['Sem recapagem', $retreads['none']],
            ['R1', $retreads['r1']],
            ['R2', $retreads['r2']],
            ['R3+', $retreads['r3plus']],
            [],
            ['Eventos do periodo', 'Quantidade'],
            ['Entradas', $events['entries']],
            ['Instalacoes', $events['installations']],
            ['Retiradas', $events['removals']],
            ['Medicoes', $events['measurements']],
            ['Recapagens', $events['retreads']],
            ['Descartes', $events['discards']],
        ];
    }

    private function inventoryRows(): array
    {
        $rows = [[
            'Codigo',
            'Status',
            'Veiculo atual',
            'Placa',
            'Marca',
            'Modelo',
            'Recapagens',
            'Sulco atual',
            'Referencia',
        ]];

        foreach ($this->data['tires'] as $tire) {
            $rows[] = [
                $tire->code,
                $tire->status,
                $tire->activeInstallation?->vehicle?->name,
                $tire->activeInstallation?->vehicle?->plate,
                $tire->brand,
                $tire->model,
                (int) $tire->retreads_count,
                $tire->current_tread_depth,
                $tire->current_tread_source,
            ];
        }

        return $rows;
    }

    private function vehicleRows(): array
    {
        $rows = [[
            'Veiculo',
            'Placa',
            'Quantidade instalada',
            'Criticos',
            'Pneus',
        ]];

        foreach ($this->data['tiresByVehicle'] as $group) {
            $rows[] = [
                $group['vehicle']?->name,
                $group['vehicle']?->plate,
                $group['installations']->count(),
                $group['critical_count'],
                $group['installations']
                    ->map(fn ($installation) => $installation->position_code . ': ' . ($installation->tire?->code ?? '-'))
                    ->implode(' | '),
            ];
        }

        return $rows;
    }

    private function eventRows(): array
    {
        $rows = [[
            'Data',
            'Tipo',
            'Titulo',
            'Descricao',
            'Status',
        ]];

        foreach ($this->data['events'] as $event) {
            $rows[] = [
                $this->date($event['date']),
                $event['type'],
                $event['title'],
                $event['description'],
                $event['status'],
            ];
        }

        return $rows;
    }

    private function alertRows(): array
    {
        $rows = [[
            'Tipo',
            'Codigo',
            'Veiculo',
            'Status',
            'Sulco atual',
            'Ultima medicao',
        ]];

        foreach ($this->data['criticalTires'] as $tire) {
            $rows[] = [
                'Sulco critico',
                $tire->code,
                $tire->activeInstallation?->vehicle?->plate,
                $tire->status,
                $tire->current_tread_depth,
                $this->date($tire->latestMeasurement?->measured_at),
            ];
        }

        foreach ($this->data['noRecentMeasurements'] as $tire) {
            $rows[] = [
                'Sem medicao recente',
                $tire->code,
                $tire->activeInstallation?->vehicle?->plate,
                $tire->status,
                $tire->current_tread_depth,
                $this->date($tire->latestMeasurement?->measured_at),
            ];
        }

        return $rows;
    }

    private function cancelledRows(): array
    {
        $rows = [[
            'Data',
            'Tipo',
            'Registro',
            'Motivo',
            'Usuario',
            'Considerado nos indicadores?',
        ]];

        foreach ($this->data['cancelledRecords'] as $record) {
            $rows[] = [
                $this->date($record['date']),
                $record['type'],
                $record['title'],
                $record['reason'],
                $record['user'],
                'Nao',
            ];
        }

        return $rows;
    }

    private function date($date): string
    {
        return $date ? Carbon::parse($date)->format('d/m/Y') : '-';
    }
}
