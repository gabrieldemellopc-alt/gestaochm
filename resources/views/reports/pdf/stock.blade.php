<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #111827;
            font-size: 8.3px;
            line-height: 1.32;
        }

        h1, h2, h3, p {
            margin: 0;
        }

        .header {
            border-bottom: 2px solid #991b1b;
            margin-bottom: 11px;
            padding-bottom: 8px;
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
            font-size: 7.8px;
        }

        .section {
            margin-top: 12px;
            page-break-inside: avoid;
        }

        .section h2 {
            color: #0f172a;
            font-size: 12px;
            margin-bottom: 5px;
        }

        .section-note {
            color: #64748b;
            font-size: 7.8px;
            margin-bottom: 6px;
        }

        .kpi-table {
            width: 100%;
            margin: 9px 0 12px;
            border-collapse: collapse;
        }

        .kpi-table td {
            width: 11.11%;
            border: 1px solid #cbd5e1;
            padding: 5px;
            background: #f8fafc;
            vertical-align: top;
        }

        .label {
            display: block;
            color: #64748b;
            font-size: 6.8px;
            text-transform: uppercase;
        }

        .value {
            display: block;
            margin-top: 3px;
            color: #0f172a;
            font-size: 10px;
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
            padding: 3.5px 4px;
            text-align: left;
            vertical-align: top;
        }

        table.data th {
            background: #e2e8f0;
            color: #0f172a;
            font-size: 6.9px;
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
            font-size: 6.8px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .badge-normal {
            color: #065f46;
            background: #d1fae5;
        }

        .badge-low,
        .badge-warning {
            color: #92400e;
            background: #fef3c7;
        }

        .badge-zero,
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
    $qty = fn ($value) => number_format((float) $value, 2, ',', '.');
    $money = fn ($value) => 'R$ ' . number_format((float) $value, 2, ',', '.');
    $statusLabels = [
        'normal' => 'Normal',
        'low' => 'Baixo',
        'zero' => 'Zerado',
    ];
    $selectedItem = $filters['stock_item_id']
        ? $items->first(fn ($row) => (int) $row['item']->id === (int) $filters['stock_item_id'])
        : null;
    $selectedCategory = $filters['stock_category_id']
        ? $categories->firstWhere('id', $filters['stock_category_id'])
        : null;
    $selectedVehicle = $filters['vehicle_id']
        ? $vehicles->firstWhere('id', $filters['vehicle_id'])
        : null;
    $selectedProcedure = $filters['procedure_id']
        ? $procedures->firstWhere('id', $filters['procedure_id'])
        : null;
    $estimatedCostRows = $movements_period
        ->filter(fn ($movement) => $movement['cost_is_estimated'])
        ->values();
@endphp

<div class="header">
    <div class="title">Relatorio de Estoque</div>
    <div class="subtitle">
        Unidade: {{ $context['location']->name ?? '-' }} |
        Divisao: {{ $context['division']->name ?? '-' }} |
        Periodo: {{ $formatDate($filters['start_date']) }} a {{ $formatDate($filters['end_date']) }} |
        Gerado em: {{ now()->format('d/m/Y H:i') }}
    </div>
    <div class="filters">
        Item: {{ $selectedItem ? $selectedItem['item']->name : 'Todos' }} |
        Categoria: {{ $selectedCategory?->name ?? 'Todas' }} |
        Veiculo: {{ $selectedVehicle ? $selectedVehicle->name . ' - ' . $selectedVehicle->plate : 'Todos' }} |
        Procedimento: {{ $selectedProcedure?->name ?? 'Todos' }}
        @if($filters['movement_type']) | Tipo: {{ $filters['movement_type'] }} @endif
        @if($filters['only_low_stock']) | Apenas baixo estoque @endif
        @if($filters['only_zero_stock']) | Apenas zerados @endif
        @if($filters['only_stale']) | Sem movimentacao recente @endif
        @if($filters['only_maintenance_consumption']) | Apenas consumo por manutencao @endif
        @if($filters['include_cancelled']) | Cancelados em secao separada @endif
    </div>
    @if($filters['period_error'])
        <div class="subtitle danger">{{ $filters['period_error'] }}</div>
    @endif
</div>

<table class="kpi-table">
    <tr>
        <td><span class="label">Itens ativos</span><span class="value">{{ $inventory_summary['active_items'] }}</span></td>
        <td><span class="label">Abaixo min.</span><span class="value">{{ $inventory_summary['low_stock'] }}</span></td>
        <td><span class="label">Zerados</span><span class="value">{{ $inventory_summary['zero_stock'] }}</span></td>
        <td><span class="label">Valor estimado</span><span class="value">{{ $money($estimated_inventory_value) }}</span></td>
        <td><span class="label">Entradas</span><span class="value">{{ $qty($total_entries_quantity) }}</span></td>
        <td><span class="label">Saidas manuais</span><span class="value">{{ $qty($total_outputs_quantity) }}</span></td>
        <td><span class="label">Consumo manut.</span><span class="value">{{ $money($total_consumed_cost) }}</span></td>
        <td><span class="label">Reversoes</span><span class="value">{{ $reversal_movements->count() }}</span></td>
        <td><span class="label">Sem mov.</span><span class="value">{{ $stale_items->count() }}</span></td>
    </tr>
</table>

<div class="section">
    <h2>Situacao atual do estoque</h2>
    <p class="section-note">Valor estimado do estoque — referencia operacional, nao valor contabil fechado.</p>
    <table class="data">
        <thead>
            <tr>
                <th>Item</th>
                <th>Categoria</th>
                <th>Un.</th>
                <th>Saldo</th>
                <th>Minimo</th>
                <th>Status</th>
                <th>Ult. mov.</th>
                <th>Dias sem mov.</th>
                <th>Valor estimado</th>
            </tr>
        </thead>
        <tbody>
            @forelse($items as $row)
                <tr>
                    <td>{{ $row['item']->name }}</td>
                    <td>{{ $row['category']?->name ?? '-' }}</td>
                    <td>{{ $row['unit'] ?: '-' }}</td>
                    <td>{{ $qty($row['current_quantity']) }}</td>
                    <td>{{ $qty($row['minimum_quantity']) }}</td>
                    <td><span class="badge badge-{{ $row['status'] }}">{{ $statusLabels[$row['status']] ?? $row['status'] }}</span></td>
                    <td>{{ $formatDateTime($row['last_movement']?->created_at) }}</td>
                    <td>{{ $row['days_without_movement'] ?? 'Sem historico' }}</td>
                    <td>{{ $money($row['estimated_value']) }}</td>
                </tr>
            @empty
                <tr><td colspan="9" class="muted">Nenhum item encontrado.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="section">
    <h2>Entradas manuais do periodo</h2>
    <table class="data">
        <thead>
            <tr>
                <th>Data</th>
                <th>Item</th>
                <th>Quantidade</th>
                <th>Custo unit.</th>
                <th>Custo total</th>
                <th>Responsavel</th>
                <th>Observacao</th>
            </tr>
        </thead>
        <tbody>
            @forelse($manual_entries as $movement)
                <tr>
                    <td>{{ $formatDateTime($movement['date']) }}</td>
                    <td>{{ $movement['item_name'] ?? '-' }}</td>
                    <td>{{ $qty($movement['quantity']) }}</td>
                    <td>{{ $money($movement['unit_cost']) }}</td>
                    <td>{{ $money($movement['total_cost']) }} @if($movement['cost_is_estimated']) <span class="warning small">estimado</span> @endif</td>
                    <td>{{ $movement['responsible'] ?? '-' }}</td>
                    <td>{{ $movement['description'] ?: '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="muted">Nenhuma entrada manual no periodo.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="section">
    <h2>Saidas manuais do periodo</h2>
    <table class="data">
        <thead>
            <tr>
                <th>Data</th>
                <th>Item</th>
                <th>Quantidade</th>
                <th>Custo unit.</th>
                <th>Custo total</th>
                <th>Responsavel</th>
                <th>Observacao</th>
            </tr>
        </thead>
        <tbody>
            @forelse($manual_outputs as $movement)
                <tr>
                    <td>{{ $formatDateTime($movement['date']) }}</td>
                    <td>{{ $movement['item_name'] ?? '-' }}</td>
                    <td>{{ $qty($movement['quantity']) }}</td>
                    <td>{{ $money($movement['unit_cost']) }}</td>
                    <td>{{ $money($movement['total_cost']) }} @if($movement['cost_is_estimated']) <span class="warning small">estimado</span> @endif</td>
                    <td>{{ $movement['responsible'] ?? '-' }}</td>
                    <td>{{ $movement['description'] ?: '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="muted">Nenhuma saida manual no periodo.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="section">
    <h2>Consumo por manutencao</h2>
    <p class="section-note">Consumos vinculados a manutencoes validas, sem cancelados ou reversoes.</p>
    <table class="data">
        <thead>
            <tr>
                <th>Data</th>
                <th>Manut.</th>
                <th>Veiculo</th>
                <th>Placa</th>
                <th>Procedimento</th>
                <th>Item</th>
                <th>Qtd.</th>
                <th>Custo</th>
                <th>Resp.</th>
            </tr>
        </thead>
        <tbody>
            @forelse($maintenance_consumption as $movement)
                <tr>
                    <td>{{ $formatDateTime($movement['date']) }}</td>
                    <td>#{{ $movement['maintenance_id'] ?? '-' }}</td>
                    <td>{{ $movement['vehicle']?->name ?? '-' }}</td>
                    <td>{{ $movement['vehicle']?->plate ?? '-' }}</td>
                    <td>{{ $movement['procedure_name'] ?? $movement['procedure']?->name ?? '-' }}</td>
                    <td>{{ $movement['item_name'] ?? '-' }}</td>
                    <td>{{ $qty($movement['quantity']) }}</td>
                    <td>{{ $money($movement['total_cost']) }} @if($movement['cost_is_estimated']) <span class="warning small">estimado</span> @endif</td>
                    <td>{{ $movement['responsible'] ?? '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="9" class="muted">Nenhum consumo por manutencao no periodo.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="section">
    <h2>Reversoes</h2>
    <p class="section-note">Reversoes ficam separadas e nao sao tratadas como entrada operacional normal.</p>
    <table class="data">
        <thead>
            <tr>
                <th>Data</th>
                <th>Item</th>
                <th>Origem</th>
                <th>Quantidade</th>
                <th>Responsavel</th>
                <th>Observacao</th>
            </tr>
        </thead>
        <tbody>
            @forelse($reversal_movements as $movement)
                <tr>
                    <td>{{ $formatDateTime($movement['date']) }}</td>
                    <td>{{ $movement['item_name'] ?? '-' }}</td>
                    <td>{{ $movement['classification_label'] }} @if($movement['reversed_from_movement_id']) de #{{ $movement['reversed_from_movement_id'] }} @endif</td>
                    <td>{{ $qty($movement['quantity']) }}</td>
                    <td>{{ $movement['responsible'] ?? '-' }}</td>
                    <td>{{ $movement['description'] ?: '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted">Nenhuma reversao no periodo.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@if($selectedItem)
    <div class="section">
        <h2>Item filtrado: {{ $selectedItem['item']->name }}</h2>
        <table class="data">
            <thead>
                <tr>
                    <th>Saldo atual</th>
                    <th>Minimo</th>
                    <th>Valor estimado</th>
                    <th>Ultima movimentacao</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>{{ $qty($selectedItem['current_quantity']) }}</td>
                    <td>{{ $qty($selectedItem['minimum_quantity']) }}</td>
                    <td>{{ $money($selectedItem['estimated_value']) }}</td>
                    <td>{{ $formatDateTime($selectedItem['last_movement']?->created_at) }}</td>
                    <td>{{ $statusLabels[$selectedItem['status']] ?? $selectedItem['status'] }}</td>
                </tr>
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
                    <h3>Baixo estoque</h3>
                    <table class="data">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Saldo</th>
                                <th>Minimo</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($low_stock_items as $row)
                                <tr>
                                    <td>{{ $row['item']->name }}</td>
                                    <td class="warning">{{ $qty($row['current_quantity']) }}</td>
                                    <td>{{ $qty($row['minimum_quantity']) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="muted">Nenhum item abaixo do minimo.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </td>
                <td>
                    <h3>Zerados</h3>
                    <table class="data">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Categoria</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($zero_stock_items as $row)
                                <tr>
                                    <td class="danger">{{ $row['item']->name }}</td>
                                    <td>{{ $row['category']?->name ?? '-' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="2" class="muted">Nenhum item zerado.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </td>
            </tr>
        </tbody>
    </table>
</div>

<div class="section">
    <table class="two-columns">
        <tbody>
            <tr>
                <td>
                    <h3>Sem movimentacao recente</h3>
                    <table class="data">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Dias sem mov.</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($stale_items->take(15) as $row)
                                <tr>
                                    <td>{{ $row['item']->name }}</td>
                                    <td>{{ $row['days_without_movement'] ?? 'Sem historico' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="2" class="muted">Nenhum item parado pelo criterio atual.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </td>
                <td>
                    <h3>Maior consumo</h3>
                    <table class="data">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Qtd.</th>
                                <th>Custo</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($top_consumed_items->take(15) as $row)
                                <tr>
                                    <td>{{ $row['item_name'] ?? '-' }}</td>
                                    <td>{{ $qty($row['quantity']) }}</td>
                                    <td>{{ $money($row['total_cost']) }} @if($row['cost_has_estimates']) <span class="warning small">estimado</span> @endif</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="muted">Nenhum consumo por manutencao.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </td>
            </tr>
        </tbody>
    </table>
</div>

@if($estimatedCostRows->isNotEmpty())
    <div class="section">
        <h2>Custos estimados por fallback</h2>
        <p class="section-note">Movimentos abaixo usaram o custo unitario atual do item por falta de custo gravado no movimento.</p>
        <table class="data">
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Item</th>
                    <th>Tipo</th>
                    <th>Quantidade</th>
                    <th>Custo estimado</th>
                </tr>
            </thead>
            <tbody>
                @foreach($estimatedCostRows as $movement)
                    <tr>
                        <td>{{ $formatDateTime($movement['date']) }}</td>
                        <td>{{ $movement['item_name'] ?? '-' }}</td>
                        <td>{{ $movement['classification_label'] }}</td>
                        <td>{{ $qty($movement['quantity']) }}</td>
                        <td>{{ $money($movement['total_cost']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

@if($context['can_view_cancelled'] && $filters['include_cancelled'] && $cancelled_records->isNotEmpty())
    <div class="section">
        <h2>Movimentacoes canceladas</h2>
        <p class="section-note danger">Nao considerados nos indicadores operacionais.</p>
        <table class="data">
            <thead>
                <tr>
                    <th>Cancelado em</th>
                    <th>Item</th>
                    <th>Tipo</th>
                    <th>Quantidade</th>
                    <th>Custo</th>
                    <th>Motivo</th>
                    <th>Responsavel</th>
                </tr>
            </thead>
            <tbody>
                @foreach($cancelled_records as $movement)
                    <tr>
                        <td>{{ $formatDateTime($movement['cancelled_at']) }}</td>
                        <td>{{ $movement['item_name'] ?? '-' }}</td>
                        <td>{{ $movement['classification_label'] }}</td>
                        <td>{{ $qty($movement['quantity']) }}</td>
                        <td>{{ $money($movement['total_cost']) }}</td>
                        <td>{{ $movement['cancel_reason'] ?: '-' }}</td>
                        <td>{{ $movement['cancelled_by'] ?: '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
</body>
</html>
