<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #111827;
            font-size: 8.5px;
            line-height: 1.35;
        }

        h1, h2, h3, p {
            margin: 0;
        }

        .header {
            border-bottom: 2px solid #991b1b;
            margin-bottom: 12px;
            padding-bottom: 9px;
        }

        .title {
            color: #0f172a;
            font-size: 21px;
            font-weight: 700;
        }

        .subtitle {
            color: #475569;
            margin-top: 4px;
        }

        .filters {
            margin-top: 7px;
            color: #334155;
            font-size: 8px;
        }

        .section {
            margin-top: 13px;
            page-break-inside: avoid;
        }

        .section h2 {
            color: #0f172a;
            font-size: 12px;
            margin-bottom: 5px;
        }

        .section-note {
            color: #64748b;
            font-size: 8px;
            margin-bottom: 6px;
        }

        .kpi-table {
            width: 100%;
            margin: 10px 0 12px;
            border-collapse: collapse;
        }

        .kpi-table td {
            width: 10%;
            border: 1px solid #cbd5e1;
            padding: 6px;
            background: #f8fafc;
            vertical-align: top;
        }

        .label {
            display: block;
            color: #64748b;
            font-size: 7px;
            text-transform: uppercase;
        }

        .value {
            display: block;
            margin-top: 3px;
            color: #0f172a;
            font-size: 11px;
            font-weight: 700;
        }

        table.data {
            width: 100%;
            border-collapse: collapse;
            page-break-inside: auto;
        }

        table.data thead {
            display: table-header-group;
        }

        table.data tr {
            page-break-inside: avoid;
        }

        table.data th,
        table.data td {
            border: 1px solid #d1d5db;
            padding: 4px 5px;
            text-align: left;
            vertical-align: top;
        }

        table.data th {
            background: #e2e8f0;
            color: #0f172a;
            font-size: 7px;
            text-transform: uppercase;
        }

        .muted {
            color: #64748b;
        }

        .danger {
            color: #991b1b;
            font-weight: 700;
        }

        .warning {
            color: #92400e;
            font-weight: 700;
        }

        .badge {
            display: inline-block;
            border-radius: 999px;
            padding: 2px 6px;
            font-size: 7px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .badge-normal {
            color: #065f46;
            background: #d1fae5;
        }

        .badge-low {
            color: #92400e;
            background: #fef3c7;
        }

        .badge-inactive,
        .badge-danger {
            color: #991b1b;
            background: #fee2e2;
        }

        .two-columns {
            width: 100%;
            border-collapse: collapse;
        }

        .two-columns > tbody > tr > td {
            width: 50%;
            vertical-align: top;
            padding-right: 8px;
        }

        .small {
            font-size: 7px;
        }
    </style>
</head>
<body>
@php
    $filters = $applied_filters;
    $formatDate = fn ($date) => $date ? \Carbon\Carbon::parse($date)->format('d/m/Y') : '-';
    $formatDateTime = fn ($date) => $date ? \Carbon\Carbon::parse($date)->format('d/m/Y H:i') : '-';
    $liters = fn ($value) => number_format((float) $value, 1, ',', '.') . ' L';
    $money = fn ($value) => $value !== null ? 'R$ ' . number_format((float) $value, 2, ',', '.') : '-';
    $decimal = fn ($value, $places = 1) => $value !== null ? number_format((float) $value, $places, ',', '.') : '-';
    $statusLabels = [
        'normal' => 'Normal',
        'low' => 'Baixo',
        'inactive' => 'Inativo',
    ];
    $movementLabels = [
        'adjustment' => 'Ajuste',
        'reversal' => 'Reversao',
    ];
    $consumptionLabel = fn ($status) => match ($status) {
        'calculado' => 'Calculado',
        'km_invalido' => 'KM invalido',
        'horas_invalidas' => 'Horas invalidas',
        default => 'Dados insuficientes',
    };
    $selectedVehicle = $filters['vehicle_id'] ? $vehicles->firstWhere('id', $filters['vehicle_id']) : null;
    $selectedProduct = $filters['fuel_product_id'] ? $products->firstWhere('id', $filters['fuel_product_id']) : null;
    $selectedTank = $filters['fuel_tank_id'] ? $tanks->firstWhere('id', $filters['fuel_tank_id']) : null;
    $diesel = $product_balances->first(fn ($item) => str_contains(mb_strtolower(($item['slug'] ?: $item['name'])), 'diesel'));
    $arla = $product_balances->first(fn ($item) => str_contains(mb_strtolower(($item['slug'] ?: $item['name'])), 'arla'));
    $adjustmentMovements = $movements_period
        ->filter(fn ($movement) => in_array($movement->movement_type, ['adjustment', 'reversal'], true))
        ->values();
    $invalidConsumptionRows = $consumption_by_vehicle
        ->filter(fn ($row) => $row['status'] !== 'calculado')
        ->values();
    $vehicleProductSummary = $selectedVehicle
        ? $fillings_period
            ->groupBy(fn ($filling) => $filling->fuel_product_id)
            ->map(fn ($items) => [
                'product' => $items->first()?->product,
                'liters' => $items->sum(fn ($item) => (float) $item->quantity_liters),
                'cost' => $items->sum(fn ($item) => (float) $item->total_cost),
                'count' => $items->count(),
            ])
            ->values()
        : collect();
@endphp

<div class="header">
    <div class="title">Relatorio de Abastecimentos</div>
    <div class="subtitle">
        Unidade: {{ $context['location']->name ?? '-' }} |
        Divisao: {{ $context['division']->name ?? '-' }} |
        Periodo: {{ $formatDate($filters['start_date']) }} a {{ $formatDate($filters['end_date']) }} |
        Gerado em: {{ now()->format('d/m/Y H:i') }}
    </div>
    <div class="filters">
        Veiculo: {{ $selectedVehicle ? $selectedVehicle->name . ' - ' . $selectedVehicle->plate : 'Todos' }} |
        Produto: {{ $selectedProduct?->name ?? 'Todos' }} |
        Tanque: {{ $selectedTank?->name ?? 'Todos' }}
        @if($filters['only_low_tanks']) | Apenas tanques baixos @endif
        @if($filters['only_vehicles_with_consumption']) | Apenas consumo calculado @endif
        @if($filters['include_missing_counters']) | Diagnostico KM/HR @endif
        @if($filters['include_cancelled']) | Cancelados em secao separada @endif
    </div>
    @if($filters['period_error'])
        <div class="subtitle danger">{{ $filters['period_error'] }}</div>
    @endif
</div>

<table class="kpi-table">
    <tr>
        <td><span class="label">Saldo Diesel</span><span class="value">{{ $liters($diesel['balance_liters'] ?? 0) }}</span></td>
        <td><span class="label">Saldo ARLA</span><span class="value">{{ $liters($arla['balance_liters'] ?? 0) }}</span></td>
        <td><span class="label">Tanques ativos</span><span class="value">{{ $tank_summary->filter(fn ($tank) => $tank['tank']->active)->count() }}</span></td>
        <td><span class="label">Tanques baixos</span><span class="value">{{ $low_tanks->count() }}</span></td>
        <td><span class="label">Recebido</span><span class="value">{{ $liters($total_received_liters) }}</span></td>
        <td><span class="label">Abastecido</span><span class="value">{{ $liters($total_filled_liters) }}</span></td>
        <td><span class="label">Custo abast.</span><span class="value">{{ $money($total_filled_cost) }}</span></td>
        <td><span class="label">Custo medio/L</span><span class="value">{{ $average_cost_per_liter !== null ? $money($average_cost_per_liter) : '-' }}</span></td>
        <td><span class="label">Veiculos</span><span class="value">{{ $vehicles_filled_count }}</span></td>
        <td><span class="label">Sem KM/HR</span><span class="value">{{ $fillings_without_km_hr->count() }}</span></td>
    </tr>
</table>

<div class="section">
    <h2>Situacao atual dos tanques</h2>
    <p class="section-note">Esta tabela representa o saldo atual e independe do periodo selecionado.</p>
    <table class="data">
        <thead>
            <tr>
                <th>Tanque</th>
                <th>Produto</th>
                <th>Capacidade</th>
                <th>Saldo</th>
                <th>%</th>
                <th>Minimo</th>
                <th>Status</th>
                <th>Ult. receb.</th>
                <th>Ult. abast.</th>
            </tr>
        </thead>
        <tbody>
            @forelse($tank_summary as $item)
                <tr>
                    <td>{{ $item['tank']->name }}</td>
                    <td>{{ $item['product']?->name ?? '-' }}</td>
                    <td>{{ $liters($item['capacity_liters']) }}</td>
                    <td>{{ $liters($item['current_balance_liters']) }}</td>
                    <td>{{ $decimal($item['occupied_percentage']) }}%</td>
                    <td>{{ $liters($item['minimum_balance_liters']) }}</td>
                    <td><span class="badge badge-{{ $item['status'] }}">{{ $statusLabels[$item['status']] ?? $item['status'] }}</span></td>
                    <td>{{ $formatDate($item['last_receipt']?->received_at) }}</td>
                    <td>{{ $formatDateTime($item['last_filling']?->filled_at) }}</td>
                </tr>
            @empty
                <tr><td colspan="9" class="muted">Nenhum tanque encontrado.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="section">
    <h2>Recebimentos do periodo</h2>
    <table class="data">
        <thead>
            <tr>
                <th>Data</th>
                <th>Tanque</th>
                <th>Produto</th>
                <th>Fornecedor</th>
                <th>NF</th>
                <th>Litros</th>
                <th>Custo unit.</th>
                <th>Custo total</th>
                <th>Recebido por</th>
            </tr>
        </thead>
        <tbody>
            @forelse($receipts_period as $receipt)
                <tr>
                    <td>{{ $formatDate($receipt->received_at) }}</td>
                    <td>{{ $receipt->tank?->name ?? '-' }}</td>
                    <td>{{ $receipt->product?->name ?? '-' }}</td>
                    <td>{{ $receipt->supplier_name ?: '-' }}</td>
                    <td>{{ $receipt->invoice_number ?: '-' }}</td>
                    <td>{{ $liters($receipt->quantity_liters) }}</td>
                    <td>{{ $money($receipt->unit_cost) }}</td>
                    <td>{{ $money($receipt->total_cost) }}</td>
                    <td>{{ $receipt->responsible?->name ?? 'Não informado' }}</td>
                </tr>
            @empty
                <tr><td colspan="9" class="muted">Nenhum recebimento no periodo.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="section">
    <h2>Abastecimentos do periodo</h2>
    <table class="data">
        <thead>
            <tr>
                <th>Data/hora</th>
                <th>Veiculo</th>
                <th>Placa</th>
                <th>Motorista/Condutor</th>
                <th>Produto</th>
                <th>Origem/local</th>
                <th>KM/HR</th>
                <th>Litros</th>
                <th>Custo</th>
                <th>Registrado por</th>
            </tr>
        </thead>
        <tbody>
            @forelse($fillings_period as $filling)
                <tr>
                    <td>{{ $formatDateTime($filling->filled_at) }}</td>
                    <td>{{ $filling->vehicle?->name ?? '-' }}</td>
                    <td>{{ $filling->vehicle?->plate ?? '-' }}</td>
                    <td>{{ $filling->driver?->name ?? 'Não informado' }}</td>
                    <td>{{ $filling->product?->name ?? '-' }}</td>
                    <td><strong>{{ $filling->source_label }}</strong><br><span>{{ $filling->location_label }}</span></td>
                    <td>KM {{ $filling->vehicle_km !== null ? $decimal($filling->vehicle_km, 0) : '-' }} / HR {{ $filling->vehicle_hours !== null ? $decimal($filling->vehicle_hours, 1) : '-' }}</td>
                    <td>{{ $liters($filling->quantity_liters) }}</td>
                    <td>{{ $money($filling->total_cost) }}</td>
                    <td>{{ $filling->responsible?->name ?? 'Não informado' }}</td>
                </tr>
            @empty
                <tr><td colspan="10" class="muted">Nenhum abastecimento no periodo.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@if($adjustmentMovements->isNotEmpty())
    <div class="section">
        <h2>Ajustes e reversoes</h2>
        <p class="section-note">Movimentos separados do consumo operacional.</p>
        <table class="data">
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Tipo</th>
                    <th>Tanque</th>
                    <th>Produto</th>
                    <th>Litros</th>
                    <th>Saldo antes</th>
                    <th>Saldo depois</th>
                    <th>Registrado por</th>
                    <th>Obs.</th>
                </tr>
            </thead>
            <tbody>
                @foreach($adjustmentMovements as $movement)
                    <tr>
                        <td>{{ $formatDateTime($movement->created_at) }}</td>
                        <td>{{ $movementLabels[$movement->movement_type] ?? $movement->movement_type }}</td>
                        <td>{{ $movement->tank?->name ?? '-' }}</td>
                        <td>{{ $movement->product?->name ?? '-' }}</td>
                        <td>{{ $liters($movement->quantity_liters) }}</td>
                        <td>{{ $liters($movement->balance_before) }}</td>
                        <td>{{ $liters($movement->balance_after) }}</td>
                        <td>{{ $movement->responsible?->name ?? 'Não informado' }}</td>
                        <td>{{ $movement->notes ?: '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

<div class="section">
    <h2>Consumo por veiculo</h2>
    <p class="section-note">Medias aparecem apenas com leituras suficientes e crescentes. Diesel e ARLA sao separados.</p>
    <table class="data">
        <thead>
            <tr>
                <th>Veiculo</th>
                <th>Produto</th>
                <th>Abast.</th>
                <th>Litros</th>
                <th>Custo</th>
                <th>km/L</th>
                <th>L/h</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($consumption_by_vehicle as $row)
                <tr>
                    <td>{{ $row['vehicle']?->name ?? '-' }} - {{ $row['vehicle']?->plate ?? '-' }}</td>
                    <td>{{ $row['product']?->name ?? '-' }}</td>
                    <td>{{ $row['fillings_count'] }}</td>
                    <td>{{ $liters($row['total_liters']) }}</td>
                    <td>{{ $money($row['total_cost']) }}</td>
                    <td>{{ $row['km_consumption']['status'] === 'calculado' ? number_format($row['km_consumption']['value'], 2, ',', '.') : '-' }}</td>
                    <td>{{ $row['hours_consumption']['status'] === 'calculado' ? number_format($row['hours_consumption']['value'], 2, ',', '.') : '-' }}</td>
                    <td>{{ $consumptionLabel($row['status']) }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="muted">Nenhum consumo de veiculo no periodo.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@if($selectedVehicle)
    <div class="section">
        <h2>Veiculo filtrado: {{ $selectedVehicle->name }} - {{ $selectedVehicle->plate }}</h2>
        <table class="data">
            <thead>
                <tr>
                    <th>Produto</th>
                    <th>Abast.</th>
                    <th>Litros</th>
                    <th>Custo</th>
                </tr>
            </thead>
            <tbody>
                @forelse($vehicleProductSummary as $row)
                    <tr>
                        <td>{{ $row['product']?->name ?? '-' }}</td>
                        <td>{{ $row['count'] }}</td>
                        <td>{{ $liters($row['liters']) }}</td>
                        <td>{{ $money($row['cost']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="muted">Nenhum abastecimento para este veiculo no periodo.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endif

<div class="section">
    <h2>Alertas</h2>
    <table class="two-columns">
        <tbody>
            <tr>
                <td>
                    <h3>Tanques abaixo do minimo</h3>
                    <table class="data">
                        <thead>
                            <tr>
                                <th>Tanque</th>
                                <th>Produto</th>
                                <th>Saldo</th>
                                <th>Minimo</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($low_tanks as $item)
                                <tr>
                                    <td>{{ $item['tank']->name }}</td>
                                    <td>{{ $item['product']?->name ?? '-' }}</td>
                                    <td class="warning">{{ $liters($item['current_balance_liters']) }}</td>
                                    <td>{{ $liters($item['minimum_balance_liters']) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="muted">Nenhum tanque abaixo do minimo.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </td>
                <td>
                    <h3>Sem KM/HR</h3>
                    <table class="data">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Veiculo</th>
                                <th>Produto</th>
                                <th>Litros</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($fillings_without_km_hr as $filling)
                                <tr>
                                    <td>{{ $formatDateTime($filling->filled_at) }}</td>
                                    <td>{{ $filling->vehicle?->plate ?? '-' }}</td>
                                    <td>{{ $filling->product?->name ?? '-' }}</td>
                                    <td>{{ $liters($filling->quantity_liters) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="muted">Nenhum abastecimento sem KM/HR.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </td>
            </tr>
        </tbody>
    </table>
</div>

@if($invalidConsumptionRows->isNotEmpty())
    <div class="section">
        <h2>Veiculos sem base confiavel de consumo</h2>
        <table class="data">
            <thead>
                <tr>
                    <th>Veiculo</th>
                    <th>Produto</th>
                    <th>Litros</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($invalidConsumptionRows as $row)
                    <tr>
                        <td>{{ $row['vehicle']?->name ?? '-' }} - {{ $row['vehicle']?->plate ?? '-' }}</td>
                        <td>{{ $row['product']?->name ?? '-' }}</td>
                        <td>{{ $liters($row['total_liters']) }}</td>
                        <td>{{ $consumptionLabel($row['status']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

@if($context['can_view_cancelled'] && $filters['include_cancelled'] && $cancelled_records->isNotEmpty())
    <div class="section">
        <h2>Registros cancelados</h2>
        <p class="section-note danger">Nao considerados nos indicadores operacionais.</p>
        <table class="data">
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Tipo</th>
                    <th>Registro</th>
                    <th>Litros</th>
                    <th>Custo</th>
                    <th>Motivo</th>
                    <th>Responsavel</th>
                </tr>
            </thead>
            <tbody>
                @foreach($cancelled_records as $record)
                    <tr>
                        <td>{{ $formatDateTime($record['date']) }}</td>
                        <td>{{ $record['type'] }}</td>
                        <td>{{ $record['record'] }}</td>
                        <td>{{ $liters($record['quantity_liters']) }}</td>
                        <td>{{ $money($record['total_cost']) }}</td>
                        <td>{{ $record['reason'] ?: '-' }}</td>
                        <td>{{ $record['user'] ?: '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
</body>
</html>
