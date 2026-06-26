<?php

namespace App\Exports;

use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class FuelReportExport implements WithMultipleSheets
{
    public function __construct(
        private readonly array $data
    ) {
    }

    public function sheets(): array
    {
        $sheets = [
            new FuelReportArraySheet('Resumo', $this->summaryRows()),
            new FuelReportArraySheet('Tanques', $this->tankRows(), [
                'C' => '#,##0.000',
                'D' => '#,##0.000',
                'E' => '#,##0.0',
                'F' => '#,##0.000',
            ]),
            new FuelReportArraySheet('Recebimentos', $this->receiptRows(), [
                'F' => '#,##0.000',
                'G' => 'R$ #,##0.0000',
                'H' => 'R$ #,##0.00',
            ]),
            new FuelReportArraySheet('Abastecimentos', $this->fillingRows(), [
                'G' => '#,##0',
                'H' => '#,##0.00',
                'I' => '#,##0.000',
                'J' => 'R$ #,##0.0000',
                'K' => 'R$ #,##0.00',
            ]),
            new FuelReportArraySheet('Movimentos', $this->movementRows(), [
                'E' => '#,##0.000',
                'F' => '#,##0.000',
                'G' => '#,##0.000',
            ]),
            new FuelReportArraySheet('Consumo por veiculo', $this->consumptionRows(), [
                'D' => '#,##0',
                'E' => '#,##0.000',
                'F' => 'R$ #,##0.00',
                'G' => '#,##0',
                'H' => '#,##0',
                'I' => '#,##0.00',
                'J' => '#,##0.00',
                'K' => '#,##0.00',
                'L' => '#,##0.00',
            ]),
            new FuelReportArraySheet('Alertas', $this->alertRows(), [
                'D' => '#,##0.000',
                'E' => '#,##0.000',
            ]),
        ];

        if (
            ! empty($this->data['context']['can_view_cancelled'])
            && ! empty($this->data['applied_filters']['include_cancelled'])
            && $this->data['cancelled_records']->count() > 0
        ) {
            $sheets[] = new FuelReportArraySheet('Cancelados', $this->cancelledRows(), [
                'D' => '#,##0.000',
                'E' => 'R$ #,##0.00',
            ]);
        }

        return $sheets;
    }

    private function summaryRows(): array
    {
        $filters = $this->data['applied_filters'];
        $diesel = $this->productBalance('diesel');
        $arla = $this->productBalance('arla');
        $selectedVehicle = $filters['vehicle_id']
            ? $this->data['vehicles']->firstWhere('id', $filters['vehicle_id'])
            : null;
        $selectedProduct = $filters['fuel_product_id']
            ? $this->data['products']->firstWhere('id', $filters['fuel_product_id'])
            : null;
        $selectedTank = $filters['fuel_tank_id']
            ? $this->data['tanks']->firstWhere('id', $filters['fuel_tank_id'])
            : null;

        return [
            ['Campo', 'Valor'],
            ['Relatorio', 'Abastecimentos'],
            ['Unidade', $this->data['context']['location']->name ?? '-'],
            ['Divisao', $this->data['context']['division']->name ?? '-'],
            ['Periodo', $this->date($filters['start_date']) . ' a ' . $this->date($filters['end_date'])],
            ['Periodo valido?', $filters['period_is_valid'] ? 'Sim' : 'Nao'],
            ['Veiculo', $selectedVehicle ? $selectedVehicle->name . ' - ' . $selectedVehicle->plate : 'Todos'],
            ['Produto', $selectedProduct?->name ?? 'Todos'],
            ['Tanque', $selectedTank?->name ?? 'Todos'],
            ['Apenas tanque baixo?', $filters['only_low_tanks'] ? 'Sim' : 'Nao'],
            ['Apenas veiculos com consumo?', $filters['only_vehicles_with_consumption'] ? 'Sim' : 'Nao'],
            ['Incluir registros sem KM/HR?', $filters['include_missing_counters'] ? 'Sim' : 'Nao'],
            ['Incluir cancelados?', $filters['include_cancelled'] ? 'Sim' : 'Nao'],
            [],
            ['Indicador', 'Valor'],
            ['Saldo Diesel', (float) ($diesel['balance_liters'] ?? 0)],
            ['Saldo ARLA', (float) ($arla['balance_liters'] ?? 0)],
            ['Tanques ativos', $this->data['tank_summary']->filter(fn (array $tank) => $tank['tank']->active)->count()],
            ['Tanques abaixo do minimo', $this->data['low_tanks']->count()],
            ['Litros recebidos', (float) $this->data['total_received_liters']],
            ['Litros abastecidos', (float) $this->data['total_filled_liters']],
            ['Custo recebido', (float) $this->data['total_received_cost']],
            ['Custo abastecido', (float) $this->data['total_filled_cost']],
            ['Custo medio por litro', $this->data['average_cost_per_liter']],
            ['Veiculos abastecidos', $this->data['vehicles_filled_count']],
            ['Abastecimentos sem KM/HR', $this->data['fillings_without_km_hr']->count()],
        ];
    }

    private function tankRows(): array
    {
        $rows = [[
            'Tanque',
            'Produto',
            'Capacidade',
            'Saldo atual',
            'Percentual ocupado',
            'Saldo minimo',
            'Status',
            'Ultimo recebimento',
            'Ultimo abastecimento',
        ]];

        foreach ($this->data['tank_summary'] as $item) {
            $rows[] = [
                $item['tank']->name,
                $item['product']?->name,
                (float) $item['capacity_liters'],
                (float) $item['current_balance_liters'],
                (float) $item['occupied_percentage'],
                (float) $item['minimum_balance_liters'],
                $this->tankStatus($item['status']),
                $this->date($item['last_receipt']?->received_at),
                $this->dateTime($item['last_filling']?->filled_at),
            ];
        }

        return $rows;
    }

    private function receiptRows(): array
    {
        $rows = [[
            'Data',
            'Tanque',
            'Produto',
            'Fornecedor',
            'NF',
            'Litros',
            'Custo unitario',
            'Custo total',
            'Responsavel',
            'Observacao',
        ]];

        foreach ($this->data['receipts_period'] as $receipt) {
            $rows[] = [
                $this->date($receipt->received_at),
                $receipt->tank?->name,
                $receipt->product?->name,
                $receipt->supplier_name,
                $receipt->invoice_number,
                (float) $receipt->quantity_liters,
                $receipt->unit_cost !== null ? (float) $receipt->unit_cost : null,
                $receipt->total_cost !== null ? (float) $receipt->total_cost : null,
                $receipt->responsible?->name,
                $receipt->notes,
            ];
        }

        return $rows;
    }

    private function fillingRows(): array
    {
        $rows = [[
            'Data/hora',
            'Veiculo',
            'Placa',
            'Motorista',
            'Tanque',
            'Produto',
            'KM',
            'Horas',
            'Litros',
            'Custo unitario',
            'Custo total',
            'Responsavel',
            'Observacao',
        ]];

        foreach ($this->data['fillings_period'] as $filling) {
            $rows[] = [
                $this->dateTime($filling->filled_at),
                $filling->vehicle?->name,
                $filling->vehicle?->plate,
                $filling->driver?->name,
                $filling->tank?->name,
                $filling->product?->name,
                $filling->vehicle_km !== null ? (float) $filling->vehicle_km : null,
                $filling->vehicle_hours !== null ? (float) $filling->vehicle_hours : null,
                (float) $filling->quantity_liters,
                $filling->unit_cost !== null ? (float) $filling->unit_cost : null,
                $filling->total_cost !== null ? (float) $filling->total_cost : null,
                $filling->responsible?->name,
                $filling->notes,
            ];
        }

        return $rows;
    }

    private function movementRows(): array
    {
        $rows = [[
            'Data',
            'Tipo',
            'Tanque',
            'Produto',
            'Litros',
            'Saldo antes',
            'Saldo depois',
            'Responsavel',
            'Observacao',
            'Fonte',
        ]];

        foreach ($this->data['movements_period'] as $movement) {
            $rows[] = [
                $this->dateTime($movement->created_at),
                $this->movementType($movement->movement_type),
                $movement->tank?->name,
                $movement->product?->name,
                (float) $movement->quantity_liters,
                (float) $movement->balance_before,
                (float) $movement->balance_after,
                $movement->responsible?->name,
                $movement->notes,
                $movement->source_type ? class_basename($movement->source_type) . ' #' . $movement->source_id : null,
            ];
        }

        return $rows;
    }

    private function consumptionRows(): array
    {
        $rows = [[
            'Veiculo',
            'Placa',
            'Produto',
            'Quantidade de abastecimentos',
            'Litros',
            'Custo',
            'KM inicial',
            'KM final',
            'Horas inicial',
            'Horas final',
            'km/L',
            'L/h',
            'Status do calculo',
        ]];

        foreach ($this->data['consumption_by_vehicle'] as $row) {
            $rows[] = [
                $row['vehicle']?->name,
                $row['vehicle']?->plate,
                $row['product']?->name,
                $row['fillings_count'],
                (float) $row['total_liters'],
                (float) $row['total_cost'],
                $row['km_consumption']['initial'],
                $row['km_consumption']['final'],
                $row['hours_consumption']['initial'],
                $row['hours_consumption']['final'],
                $row['km_consumption']['status'] === 'calculado' ? $row['km_consumption']['value'] : null,
                $row['hours_consumption']['status'] === 'calculado' ? $row['hours_consumption']['value'] : null,
                $this->consumptionStatus($row['status']),
            ];
        }

        return $rows;
    }

    private function alertRows(): array
    {
        $rows = [[
            'Tipo',
            'Veiculo/Tanque',
            'Produto',
            'Litros',
            'Referencia',
            'Status',
        ]];

        foreach ($this->data['low_tanks'] as $item) {
            $rows[] = [
                'Tanque baixo',
                $item['tank']->name,
                $item['product']?->name,
                (float) $item['current_balance_liters'],
                (float) $item['minimum_balance_liters'],
                'Abaixo do minimo',
            ];
        }

        foreach ($this->data['fillings_without_km_hr'] as $filling) {
            $rows[] = [
                'Abastecimento sem KM/HR',
                trim(($filling->vehicle?->name ?? '-') . ' - ' . ($filling->vehicle?->plate ?? '-')),
                $filling->product?->name,
                (float) $filling->quantity_liters,
                $this->dateTime($filling->filled_at),
                'Sem KM/HR',
            ];
        }

        foreach ($this->data['consumption_by_vehicle'] as $row) {
            if ($row['status'] === 'calculado') {
                continue;
            }

            $rows[] = [
                $this->consumptionStatus($row['status']),
                trim(($row['vehicle']?->name ?? '-') . ' - ' . ($row['vehicle']?->plate ?? '-')),
                $row['product']?->name,
                (float) $row['total_liters'],
                null,
                $this->consumptionStatus($row['status']),
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
            'Litros',
            'Custo',
            'Motivo',
            'Responsavel',
            'Considerado nos indicadores?',
        ]];

        foreach ($this->data['cancelled_records'] as $record) {
            $rows[] = [
                $this->dateTime($record['date']),
                $record['type'],
                $record['record'],
                (float) $record['quantity_liters'],
                $record['total_cost'] !== null ? (float) $record['total_cost'] : null,
                $record['reason'],
                $record['user'],
                'Nao',
            ];
        }

        return $rows;
    }

    private function productBalance(string $needle): ?array
    {
        return $this->data['product_balances']
            ->first(fn (array $item) => str_contains(mb_strtolower((string) ($item['slug'] ?: $item['name'])), $needle));
    }

    private function date($date): string
    {
        return $date ? Carbon::parse($date)->format('d/m/Y') : '-';
    }

    private function dateTime($date): string
    {
        return $date ? Carbon::parse($date)->format('d/m/Y H:i') : '-';
    }

    private function tankStatus(?string $status): string
    {
        return match ($status) {
            'normal' => 'Normal',
            'low' => 'Baixo',
            'inactive' => 'Inativo',
            default => (string) $status,
        };
    }

    private function movementType(?string $type): string
    {
        return match ($type) {
            'receipt' => 'Recebimento',
            'filling' => 'Abastecimento',
            'adjustment' => 'Ajuste',
            'reversal' => 'Reversao',
            default => (string) $type,
        };
    }

    private function consumptionStatus(?string $status): string
    {
        return match ($status) {
            'calculado' => 'Calculado',
            'km_invalido' => 'KM invalido',
            'horas_invalidas' => 'Horas invalidas',
            default => 'Dados insuficientes',
        };
    }
}
