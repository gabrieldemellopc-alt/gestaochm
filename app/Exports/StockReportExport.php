<?php

namespace App\Exports;

use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class StockReportExport implements WithMultipleSheets
{
    public function __construct(
        private readonly array $data
    ) {
    }

    public function sheets(): array
    {
        $sheets = [
            new StockReportArraySheet('Resumo', $this->summaryRows()),
            new StockReportArraySheet('Estoque atual', $this->inventoryRows(), [
                'D' => '#,##0.00',
                'E' => '#,##0.00',
                'I' => 'R$ #,##0.00',
            ]),
            new StockReportArraySheet('Movimentos', $this->movementRows(), [
                'E' => '#,##0.00',
                'F' => 'R$ #,##0.00',
                'G' => 'R$ #,##0.00',
            ]),
            new StockReportArraySheet('Entradas', $this->manualEntryRows(), [
                'D' => '#,##0.00',
                'E' => 'R$ #,##0.00',
                'F' => 'R$ #,##0.00',
            ]),
            new StockReportArraySheet('Saidas', $this->manualOutputRows(), [
                'D' => '#,##0.00',
                'E' => 'R$ #,##0.00',
                'F' => 'R$ #,##0.00',
            ]),
            new StockReportArraySheet('Consumo manutencao', $this->maintenanceConsumptionRows(), [
                'G' => '#,##0.00',
                'H' => 'R$ #,##0.00',
                'I' => 'R$ #,##0.00',
            ]),
            new StockReportArraySheet('Reversoes', $this->reversalRows(), [
                'D' => '#,##0.00',
                'E' => 'R$ #,##0.00',
            ]),
            new StockReportArraySheet('Alertas', $this->alertRows(), [
                'D' => '#,##0.00',
                'E' => '#,##0.00',
                'F' => 'R$ #,##0.00',
            ]),
        ];

        if (
            ! empty($this->data['context']['can_view_cancelled'])
            && ! empty($this->data['applied_filters']['include_cancelled'])
            && $this->data['cancelled_records']->count() > 0
        ) {
            $sheets[] = new StockReportArraySheet('Cancelados', $this->cancelledRows(), [
                'D' => '#,##0.00',
                'E' => 'R$ #,##0.00',
                'F' => 'R$ #,##0.00',
            ]);
        }

        return $sheets;
    }

    private function summaryRows(): array
    {
        $filters = $this->data['applied_filters'];
        $selectedItem = $filters['stock_item_id']
            ? $this->data['items']->first(fn (array $row) => (int) $row['item']->id === (int) $filters['stock_item_id'])
            : null;
        $selectedCategory = $filters['stock_category_id']
            ? $this->data['categories']->firstWhere('id', $filters['stock_category_id'])
            : null;
        $selectedVehicle = $filters['vehicle_id']
            ? $this->data['vehicles']->firstWhere('id', $filters['vehicle_id'])
            : null;
        $selectedProcedure = $filters['procedure_id']
            ? $this->data['procedures']->firstWhere('id', $filters['procedure_id'])
            : null;

        return [
            ['Campo', 'Valor'],
            ['Relatorio', 'Estoque'],
            ['Unidade', $this->data['context']['location']->name ?? '-'],
            ['Divisao', $this->data['context']['division']->name ?? '-'],
            ['Periodo', $this->date($filters['start_date']) . ' a ' . $this->date($filters['end_date'])],
            ['Periodo valido?', $filters['period_is_valid'] ? 'Sim' : 'Nao'],
            ['Item', $selectedItem ? $selectedItem['item']->name : 'Todos'],
            ['Categoria', $selectedCategory?->name ?? 'Todas'],
            ['Tipo de movimento', $this->movementFilterLabel($filters['movement_type'])],
            ['Veiculo', $selectedVehicle ? $selectedVehicle->name . ' - ' . $selectedVehicle->plate : 'Todos'],
            ['Procedimento', $selectedProcedure?->name ?? 'Todos'],
            ['Apenas baixo estoque?', $filters['only_low_stock'] ? 'Sim' : 'Nao'],
            ['Apenas zerados?', $filters['only_zero_stock'] ? 'Sim' : 'Nao'],
            ['Apenas sem movimentacao recente?', $filters['only_stale'] ? 'Sim' : 'Nao'],
            ['Apenas consumo por manutencao?', $filters['only_maintenance_consumption'] ? 'Sim' : 'Nao'],
            ['Incluir cancelados?', $filters['include_cancelled'] ? 'Sim' : 'Nao'],
            [],
            ['Indicador', 'Valor'],
            ['Itens ativos', $this->data['inventory_summary']['active_items']],
            ['Itens abaixo do minimo', $this->data['inventory_summary']['low_stock']],
            ['Itens zerados', $this->data['inventory_summary']['zero_stock']],
            ['Valor estimado do estoque', (float) $this->data['estimated_inventory_value']],
            ['Entradas', (float) $this->data['total_entries_quantity']],
            ['Saidas manuais', (float) $this->data['total_outputs_quantity']],
            ['Consumo por manutencao', (float) $this->data['total_consumed_cost']],
            ['Reversoes', $this->data['reversal_movements']->count()],
            ['Itens sem movimentacao recente', $this->data['stale_items']->count()],
            [],
            ['Nota', $this->data['estimated_inventory_value_note']],
        ];
    }

    private function inventoryRows(): array
    {
        $rows = [[
            'Item',
            'Categoria',
            'Unidade',
            'Saldo atual',
            'Estoque minimo',
            'Status',
            'Ultimo movimento',
            'Dias sem movimentacao',
            'Valor estimado',
            'Custo e estimado?',
        ]];

        foreach ($this->data['items'] as $row) {
            $rows[] = [
                $row['item']->name,
                $row['category']?->name,
                $row['unit'],
                (float) $row['current_quantity'],
                (float) $row['minimum_quantity'],
                $this->stockStatus($row['status']),
                $this->dateTime($row['last_movement']?->created_at),
                $row['days_without_movement'],
                (float) $row['estimated_value'],
                'Sim',
            ];
        }

        return $rows;
    }

    private function movementRows(): array
    {
        $rows = [[
            'Data',
            'Tipo',
            'Item',
            'Categoria',
            'Quantidade',
            'Custo unitario',
            'Custo total',
            'Responsavel',
            'Observacao',
            'Manutencao',
            'Veiculo',
            'Procedimento',
            'Custo estimado?',
        ]];

        foreach ($this->data['movements_period'] as $movement) {
            $rows[] = $this->movementRow($movement);
        }

        return $rows;
    }

    private function manualEntryRows(): array
    {
        return $this->rowsFromMovements($this->data['manual_entries']);
    }

    private function manualOutputRows(): array
    {
        return $this->rowsFromMovements($this->data['manual_outputs']);
    }

    private function maintenanceConsumptionRows(): array
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
            'Custo total',
            'Custo estimado?',
        ]];

        foreach ($this->data['maintenance_consumption'] as $movement) {
            $rows[] = [
                $this->dateTime($movement['date']),
                $movement['maintenance_id'] ? '#' . $movement['maintenance_id'] : null,
                $movement['vehicle']?->name,
                $movement['vehicle']?->plate,
                $movement['procedure_name'] ?? $movement['procedure']?->name,
                $movement['item_name'],
                (float) $movement['quantity'],
                (float) $movement['unit_cost'],
                (float) $movement['total_cost'],
                $movement['cost_is_estimated'] ? 'Sim' : 'Nao',
            ];
        }

        return $rows;
    }

    private function reversalRows(): array
    {
        $rows = [[
            'Data',
            'Item',
            'Origem',
            'Quantidade',
            'Custo',
            'Responsavel',
            'Observacao',
        ]];

        foreach ($this->data['reversal_movements'] as $movement) {
            $rows[] = [
                $this->dateTime($movement['date']),
                $movement['item_name'],
                trim($movement['classification_label'] . ($movement['reversed_from_movement_id'] ? ' de #' . $movement['reversed_from_movement_id'] : '')),
                (float) $movement['quantity'],
                (float) $movement['total_cost'],
                $movement['responsible'],
                $movement['description'],
            ];
        }

        return $rows;
    }

    private function alertRows(): array
    {
        $rows = [[
            'Tipo',
            'Item',
            'Categoria',
            'Quantidade',
            'Referencia',
            'Custo/valor',
            'Observacao',
        ]];

        foreach ($this->data['low_stock_items'] as $row) {
            $rows[] = [
                'Baixo estoque',
                $row['item']->name,
                $row['category']?->name,
                (float) $row['current_quantity'],
                (float) $row['minimum_quantity'],
                (float) $row['estimated_value'],
                'Abaixo do estoque minimo',
            ];
        }

        foreach ($this->data['zero_stock_items'] as $row) {
            $rows[] = [
                'Zerado',
                $row['item']->name,
                $row['category']?->name,
                (float) $row['current_quantity'],
                (float) $row['minimum_quantity'],
                (float) $row['estimated_value'],
                'Saldo zerado',
            ];
        }

        foreach ($this->data['stale_items'] as $row) {
            $rows[] = [
                'Sem movimentacao recente',
                $row['item']->name,
                $row['category']?->name,
                (float) $row['current_quantity'],
                $row['days_without_movement'],
                (float) $row['estimated_value'],
                'Item sem movimentacao dentro do criterio do relatorio',
            ];
        }

        foreach ($this->data['top_consumed_items'] as $row) {
            $rows[] = [
                'Maior consumo',
                $row['item_name'],
                $row['category_name'],
                (float) $row['quantity'],
                null,
                (float) $row['total_cost'],
                $row['cost_has_estimates'] ? 'Possui custo estimado por fallback' : 'Consumo por manutencao',
            ];
        }

        foreach ($this->data['movements_period']->filter(fn (array $movement) => $movement['cost_is_estimated']) as $movement) {
            $rows[] = [
                'Custo estimado',
                $movement['item_name'],
                $movement['category_name'],
                (float) $movement['quantity'],
                null,
                (float) $movement['total_cost'],
                $movement['cost_note'],
            ];
        }

        return $rows;
    }

    private function cancelledRows(): array
    {
        $rows = [[
            'Cancelado em',
            'Tipo',
            'Item',
            'Quantidade',
            'Custo unitario',
            'Custo total',
            'Motivo',
            'Responsavel',
            'Considerado nos indicadores?',
        ]];

        foreach ($this->data['cancelled_records'] as $movement) {
            $rows[] = [
                $this->dateTime($movement['cancelled_at']),
                $movement['classification_label'],
                $movement['item_name'],
                (float) $movement['quantity'],
                (float) $movement['unit_cost'],
                (float) $movement['total_cost'],
                $movement['cancel_reason'],
                $movement['cancelled_by'],
                'Nao',
            ];
        }

        return $rows;
    }

    private function rowsFromMovements($movements): array
    {
        $rows = [[
            'Data',
            'Item',
            'Categoria',
            'Quantidade',
            'Custo unitario',
            'Custo total',
            'Responsavel',
            'Observacao',
            'Custo estimado?',
        ]];

        foreach ($movements as $movement) {
            $rows[] = [
                $this->dateTime($movement['date']),
                $movement['item_name'],
                $movement['category_name'],
                (float) $movement['quantity'],
                (float) $movement['unit_cost'],
                (float) $movement['total_cost'],
                $movement['responsible'],
                $movement['description'],
                $movement['cost_is_estimated'] ? 'Sim' : 'Nao',
            ];
        }

        return $rows;
    }

    private function movementRow(array $movement): array
    {
        return [
            $this->dateTime($movement['date']),
            $movement['classification_label'],
            $movement['item_name'],
            $movement['category_name'],
            (float) $movement['quantity'],
            (float) $movement['unit_cost'],
            (float) $movement['total_cost'],
            $movement['responsible'],
            $movement['description'],
            $movement['maintenance_id'] ? '#' . $movement['maintenance_id'] : null,
            $movement['vehicle'] ? trim(($movement['vehicle']->name ?? '-') . ' - ' . ($movement['vehicle']->plate ?? '-')) : null,
            $movement['procedure_name'] ?? $movement['procedure']?->name,
            $movement['cost_is_estimated'] ? 'Sim' : 'Nao',
        ];
    }

    private function date($date): string
    {
        return $date ? Carbon::parse($date)->format('d/m/Y') : '-';
    }

    private function dateTime($date): string
    {
        return $date ? Carbon::parse($date)->format('d/m/Y H:i') : '-';
    }

    private function stockStatus(?string $status): string
    {
        return match ($status) {
            'normal' => 'Normal',
            'low' => 'Baixo',
            'zero' => 'Zerado',
            default => (string) $status,
        };
    }

    private function movementFilterLabel(?string $type): string
    {
        return match ($type) {
            'in' => 'Entrada manual',
            'out' => 'Saida manual',
            'maintenance' => 'Consumo por manutencao',
            'reversal' => 'Reversao',
            'cancelled' => 'Cancelados',
            default => 'Todos',
        };
    }
}
