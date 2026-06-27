@extends('layouts.app')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/pages/reports.css') }}?v=6">
@endpush

@section('content')
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
    $statusClass = fn ($status) => match ($status) {
        'zero' => 'danger',
        'low' => 'warning',
        default => 'normal',
    };
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

<div class="reports-page reports-tires-page reports-stock-page stock-full-report">
    <div class="reports-hero reports-tires-hero">
        <div>
            <div class="reports-hero-badge">Relatorio completo</div>
            <h1>Relatorio Completo de Estoque</h1>
            <p>
                Unidade {{ $context['location']->name ?? '-' }} /
                divisao {{ $context['division']->name ?? '-' }} no periodo
                {{ $formatDate($filters['start_date']) }} a {{ $formatDate($filters['end_date']) }}.
            </p>
        </div>

        <div class="tire-action-row">
            <a href="{{ route('reports.stock.index', request()->query()) }}" class="report-module-button secondary">
                Voltar ao painel
            </a>
            <button type="button" class="report-module-button secondary" disabled>
                Exportar PDF
            </button>
            <button type="button" class="report-module-button secondary" disabled>
                Exportar Excel
            </button>
        </div>
    </div>

    <section class="tire-report-section stock-report-context">
        <div class="fuel-meta-grid stock-meta-grid">
            <div>
                <span>Unidade</span>
                <strong>{{ $context['location']->name ?? '-' }}</strong>
            </div>
            <div>
                <span>Divisao</span>
                <strong>{{ $context['division']->name ?? '-' }}</strong>
            </div>
            <div>
                <span>Periodo</span>
                <strong>{{ $formatDate($filters['start_date']) }} a {{ $formatDate($filters['end_date']) }}</strong>
            </div>
            <div>
                <span>Item</span>
                <strong>{{ $selectedItem ? $selectedItem['item']->name : 'Todos' }}</strong>
            </div>
        </div>

        <div class="fuel-filter-chip-list stock-filter-chip-list">
            <span>Categoria: {{ $selectedCategory?->name ?? 'Todas' }}</span>
            <span>Veiculo: {{ $selectedVehicle ? $selectedVehicle->name . ' - ' . $selectedVehicle->plate : 'Todos' }}</span>
            <span>Procedimento: {{ $selectedProcedure?->name ?? 'Todos' }}</span>
            @if($filters['only_low_stock'])
                <span>Apenas baixo estoque</span>
            @endif
            @if($filters['only_zero_stock'])
                <span>Apenas zerados</span>
            @endif
            @if($filters['only_stale'])
                <span>Apenas sem movimentacao recente</span>
            @endif
            @if($filters['only_maintenance_consumption'])
                <span>Apenas consumo por manutencao</span>
            @endif
            @if($filters['include_cancelled'])
                <span>Cancelados em secao separada</span>
            @endif
        </div>
    </section>

    @if($filters['period_error'])
        <div class="tire-report-alert">{{ $filters['period_error'] }}</div>
    @endif

    <section class="tire-report-section">
        <div class="tire-section-header">
            <div>
                <span class="tire-section-kicker">Situacao atual</span>
                <h2>Estoque da unidade</h2>
                <p>{{ $estimated_inventory_value_note }}</p>
            </div>
        </div>

        <div class="tire-report-table-wrap">
            <table class="tire-report-table stock-detail-table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Categoria</th>
                        <th>Unidade</th>
                        <th>Saldo atual</th>
                        <th>Estoque minimo</th>
                        <th>Status</th>
                        <th>Ultimo movimento</th>
                        <th>Dias sem mov.</th>
                        <th>Valor estimado</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $row)
                        <tr class="stock-status-row stock-status-{{ $row['status'] }}">
                            <td><strong>{{ $row['item']->name }}</strong></td>
                            <td>{{ $row['category']?->name ?? '-' }}</td>
                            <td>{{ $row['unit'] ?: '-' }}</td>
                            <td>{{ $qty($row['current_quantity']) }}</td>
                            <td>{{ $qty($row['minimum_quantity']) }}</td>
                            <td>
                                <span class="fuel-status-badge status-{{ $statusClass($row['status']) }}">
                                    {{ $statusLabels[$row['status']] ?? $row['status'] }}
                                </span>
                            </td>
                            <td>{{ $formatDateTime($row['last_movement']?->created_at) }}</td>
                            <td>{{ $row['days_without_movement'] ?? 'Sem historico' }}</td>
                            <td>{{ $money($row['estimated_value']) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="empty-state">Nenhum item encontrado para os filtros aplicados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="tire-report-section">
        <div class="tire-section-header">
            <div>
                <span class="tire-section-kicker">Eventos do periodo</span>
                <h2>Entradas manuais</h2>
                <p>Entradas operacionais validas. Reversoes ficam em bloco proprio.</p>
            </div>
        </div>

        <div class="tire-report-table-wrap">
            <table class="tire-report-table stock-detail-table">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Item</th>
                        <th>Categoria</th>
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
                            <td>{{ $movement['category_name'] ?? '-' }}</td>
                            <td>{{ $qty($movement['quantity']) }}</td>
                            <td>{{ $money($movement['unit_cost']) }}</td>
                            <td>{{ $money($movement['total_cost']) }} @if($movement['cost_is_estimated']) <small>estimado</small> @endif</td>
                            <td>{{ $movement['responsible'] ?? '-' }}</td>
                            <td>{{ $movement['description'] ?: '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="empty-state">Nenhuma entrada manual no periodo.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="tire-report-section">
        <div class="tire-section-header">
            <div>
                <h2>Saidas manuais</h2>
                <p>Saidas manuais separadas do consumo por manutencao.</p>
            </div>
        </div>

        <div class="tire-report-table-wrap">
            <table class="tire-report-table stock-detail-table">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Item</th>
                        <th>Categoria</th>
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
                            <td>{{ $movement['category_name'] ?? '-' }}</td>
                            <td>{{ $qty($movement['quantity']) }}</td>
                            <td>{{ $money($movement['unit_cost']) }}</td>
                            <td>{{ $money($movement['total_cost']) }} @if($movement['cost_is_estimated']) <small>estimado</small> @endif</td>
                            <td>{{ $movement['responsible'] ?? '-' }}</td>
                            <td>{{ $movement['description'] ?: '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="empty-state">Nenhuma saida manual no periodo.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="tire-report-section">
        <div class="tire-section-header">
            <div>
                <h2>Consumo por manutencao</h2>
                <p>Consumos vinculados a manutencoes validas, sem registros cancelados ou revertidos.</p>
            </div>
        </div>

        <div class="tire-report-table-wrap">
            <table class="tire-report-table stock-detail-table">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Manutencao</th>
                        <th>Veiculo</th>
                        <th>Placa</th>
                        <th>Procedimento</th>
                        <th>Item</th>
                        <th>Quantidade</th>
                        <th>Custo unit.</th>
                        <th>Custo total</th>
                        <th>Responsavel</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($maintenance_consumption as $movement)
                        <tr>
                            <td>{{ $formatDateTime($movement['date']) }}</td>
                            <td>#{{ $movement['maintenance_id'] ?? '-' }}</td>
                            <td>{{ $movement['vehicle']?->name ?? '-' }}</td>
                            <td>{{ $movement['vehicle']?->plate ?? '-' }}</td>
                            <td>{{ $movement['procedure']?->name ?? '-' }}</td>
                            <td>{{ $movement['item_name'] ?? '-' }}</td>
                            <td>{{ $qty($movement['quantity']) }}</td>
                            <td>{{ $money($movement['unit_cost']) }}</td>
                            <td>{{ $money($movement['total_cost']) }} @if($movement['cost_is_estimated']) <small>estimado</small> @endif</td>
                            <td>{{ $movement['responsible'] ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="empty-state">Nenhum consumo por manutencao no periodo.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="tire-report-section">
        <div class="tire-section-header">
            <div>
                <h2>Reversoes</h2>
                <p>Reversoes ficam separadas e nao sao tratadas como entrada operacional normal.</p>
            </div>
        </div>

        <div class="tire-report-table-wrap">
            <table class="tire-report-table stock-detail-table">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Item</th>
                        <th>Tipo/origem</th>
                        <th>Quantidade</th>
                        <th>Saldo anterior</th>
                        <th>Saldo posterior</th>
                        <th>Responsavel</th>
                        <th>Observacao</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($reversal_movements as $movement)
                        <tr class="stock-reversal-row">
                            <td>{{ $formatDateTime($movement['date']) }}</td>
                            <td>{{ $movement['item_name'] ?? '-' }}</td>
                            <td>{{ $movement['classification_label'] }} @if($movement['reversed_from_movement_id']) de #{{ $movement['reversed_from_movement_id'] }} @endif</td>
                            <td>{{ $qty($movement['quantity']) }}</td>
                            <td>-</td>
                            <td>-</td>
                            <td>{{ $movement['responsible'] ?? '-' }}</td>
                            <td>{{ $movement['description'] ?: '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="empty-state">Nenhuma reversao no periodo.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if($selectedItem)
        <section class="tire-report-section stock-item-focus">
            <div class="tire-section-header">
                <div>
                    <span class="tire-section-kicker">Visao individual</span>
                    <h2>{{ $selectedItem['item']->name }}</h2>
                    <p>Resumo do item filtrado no periodo selecionado.</p>
                </div>
            </div>

            <div class="retread-summary-grid compact">
                <div><span>Saldo atual</span><strong>{{ $qty($selectedItem['current_quantity']) }}</strong></div>
                <div><span>Minimo</span><strong>{{ $qty($selectedItem['minimum_quantity']) }}</strong></div>
                <div><span>Valor estimado</span><strong>{{ $money($selectedItem['estimated_value']) }}</strong></div>
                <div><span>Ultima movimentacao</span><strong>{{ $formatDateTime($selectedItem['last_movement']?->created_at) }}</strong></div>
            </div>

            <div class="tire-report-columns stock-panel-columns">
                <div>
                    <h3 class="tire-subtitle">Movimentos do item</h3>
                    <div class="tire-compact-list">
                        @forelse($movements_period->take(8) as $movement)
                            <div class="tire-compact-item">
                                <strong>{{ $movement['classification_label'] }} - {{ $formatDateTime($movement['date']) }}</strong>
                                <span>{{ $qty($movement['quantity']) }} | {{ $money($movement['total_cost']) }}</span>
                            </div>
                        @empty
                            <div class="empty-state">Nenhum movimento para este item no periodo.</div>
                        @endforelse
                    </div>
                </div>

                <div>
                    <h3 class="tire-subtitle">Alertas do item</h3>
                    <div class="tire-compact-list">
                        <div class="tire-compact-item {{ $selectedItem['status'] === 'normal' ? '' : 'warning' }}">
                            <strong>Status: {{ $statusLabels[$selectedItem['status']] ?? $selectedItem['status'] }}</strong>
                            <span>{{ $selectedItem['estimated_value_note'] }}</span>
                        </div>
                        @if($selectedItem['is_stale'])
                            <div class="tire-compact-item warning">
                                <strong>Sem movimentacao recente</strong>
                                <span>{{ $selectedItem['days_without_movement'] ?? 'Sem historico' }} dias sem movimento.</span>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </section>
    @endif

    <section class="tire-report-section">
        <div class="tire-section-header">
            <div>
                <span class="tire-section-kicker">Alertas</span>
                <h2>Pontos de atencao</h2>
                <p>Itens criticos e custos estimados pedem conferencia antes de decisao financeira.</p>
            </div>
        </div>

        <div class="tire-report-columns stock-panel-columns">
            <div>
                <h3 class="tire-subtitle">Itens abaixo do minimo</h3>
                <div class="tire-compact-list">
                    @forelse($low_stock_items as $row)
                        <div class="tire-compact-item warning">
                            <strong>{{ $row['item']->name }}</strong>
                            <span>{{ $qty($row['current_quantity']) }} | minimo {{ $qty($row['minimum_quantity']) }}</span>
                        </div>
                    @empty
                        <div class="empty-state">Nenhum item abaixo do minimo.</div>
                    @endforelse
                </div>
            </div>

            <div>
                <h3 class="tire-subtitle">Itens zerados</h3>
                <div class="tire-compact-list">
                    @forelse($zero_stock_items as $row)
                        <div class="tire-compact-item danger">
                            <strong>{{ $row['item']->name }}</strong>
                            <span>{{ $row['category']?->name ?? '-' }}</span>
                        </div>
                    @empty
                        <div class="empty-state">Nenhum item zerado.</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="tire-report-columns stock-panel-columns">
            <div>
                <h3 class="tire-subtitle">Sem movimentacao recente</h3>
                <div class="tire-compact-list">
                    @forelse($stale_items->take(10) as $row)
                        <div class="tire-compact-item warning">
                            <strong>{{ $row['item']->name }}</strong>
                            <span>{{ $row['days_without_movement'] ?? 'Sem historico' }} dias sem movimento.</span>
                        </div>
                    @empty
                        <div class="empty-state">Nenhum item parado pelo criterio atual.</div>
                    @endforelse
                </div>
            </div>

            <div>
                <h3 class="tire-subtitle">Custos estimados por fallback</h3>
                <div class="tire-compact-list">
                    @forelse($estimatedCostRows->take(10) as $movement)
                        <div class="tire-compact-item warning">
                            <strong>{{ $movement['item_name'] ?? '-' }}</strong>
                            <span>{{ $movement['cost_note'] ?? 'Custo estimado.' }}</span>
                        </div>
                    @empty
                        <div class="empty-state">Nenhum custo estimado por fallback no periodo.</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="tire-report-table-wrap stock-alert-table">
            <table class="tire-report-table stock-detail-table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Categoria</th>
                        <th>Quantidade</th>
                        <th>Custo</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($top_consumed_items as $row)
                        <tr>
                            <td>{{ $row['item_name'] ?? '-' }}</td>
                            <td>{{ $row['category_name'] ?? '-' }}</td>
                            <td>{{ $qty($row['quantity']) }}</td>
                            <td>{{ $money($row['total_cost']) }} @if($row['cost_has_estimates']) <small>estimado</small> @endif</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="empty-state">Nenhum item consumido em manutencao no periodo.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if($context['can_view_cancelled'] && $filters['include_cancelled'] && $cancelled_records->isNotEmpty())
        <section class="tire-report-section cancelled-section">
            <div class="tire-section-header">
                <div>
                    <span class="tire-section-kicker">Separado dos indicadores</span>
                    <h2>Movimentacoes canceladas</h2>
                    <p>Nao consideradas nos indicadores operacionais, saldo, entradas, saidas, consumo, custo ou rankings.</p>
                </div>
            </div>

            <div class="tire-report-table-wrap">
                <table class="tire-report-table stock-detail-table">
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
        </section>
    @endif
</div>
@endsection
