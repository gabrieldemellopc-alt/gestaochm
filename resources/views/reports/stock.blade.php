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
    $movementLabels = [
        '' => 'Todos',
        'in' => 'Entrada manual',
        'out' => 'Saida manual',
        'maintenance' => 'Consumo por manutencao',
        'reversal' => 'Reversao',
        'cancelled' => 'Cancelados',
    ];
    $topConsumed = $top_consumed_items->first();
@endphp

<div class="reports-page reports-tires-page reports-stock-page tire-summary-dashboard">
    <div class="reports-hero reports-tires-hero">
        <div>
            <div class="reports-hero-badge">Central de Estoque</div>
            <h1>Painel de Estoque</h1>
            <p>
                Resumo operacional de itens, movimentacoes e consumo em
                {{ $context['location']->name ?? 'unidade ativa' }}.
            </p>
        </div>

        <div class="tire-action-row">
            <a href="{{ route('reports.stock.full', request()->query()) }}" class="report-module-button">
                Visualizar relatorio completo
            </a>
            <button type="button" class="report-module-button secondary" disabled>
                Exportar PDF
            </button>
            <button type="button" class="report-module-button secondary" disabled>
                Exportar Excel
            </button>
        </div>
    </div>

    <form method="GET" action="{{ route('reports.stock.index') }}" class="tire-report-filters">
        <div class="tire-filter-grid">
            <label>
                Data inicial
                <input type="date" name="start_date" value="{{ $filters['start_date']->toDateString() }}">
            </label>

            <label>
                Data final
                <input type="date" name="end_date" value="{{ $filters['end_date']->toDateString() }}">
            </label>

            <label>
                Item
                <select name="stock_item_id">
                    <option value="">Todos os itens</option>
                    @foreach($items as $row)
                        <option value="{{ $row['item']->id }}" @selected((int) $filters['stock_item_id'] === (int) $row['item']->id)>
                            {{ $row['item']->name }}
                        </option>
                    @endforeach
                </select>
            </label>

            <label>
                Categoria
                <select name="category">
                    <option value="">Todas as categorias</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" @selected((int) $filters['stock_category_id'] === (int) $category->id)>
                            {{ $category->name }}
                        </option>
                    @endforeach
                </select>
            </label>

            <label>
                Tipo de movimento
                <select name="movement_type">
                    @foreach($movementLabels as $value => $label)
                        <option value="{{ $value }}" @selected($filters['movement_type'] === ($value ?: null))>{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <label>
                Veiculo
                <select name="vehicle_id">
                    <option value="">Todos os veiculos</option>
                    @foreach($vehicles as $vehicle)
                        <option value="{{ $vehicle->id }}" @selected((int) $filters['vehicle_id'] === (int) $vehicle->id)>
                            {{ $vehicle->name }} - {{ $vehicle->plate }}
                        </option>
                    @endforeach
                </select>
            </label>

            <label>
                Procedimento
                <select name="procedure_id">
                    <option value="">Todos os procedimentos</option>
                    @foreach($procedures as $procedure)
                        <option value="{{ $procedure->id }}" @selected((int) $filters['procedure_id'] === (int) $procedure->id)>
                            {{ $procedure->name }}
                        </option>
                    @endforeach
                </select>
            </label>
        </div>

        <div class="tire-filter-checks">
            <label>
                <input type="checkbox" name="only_low_stock" value="1" @checked($filters['only_low_stock'])>
                Apenas baixo estoque
            </label>

            <label>
                <input type="checkbox" name="only_zero_stock" value="1" @checked($filters['only_zero_stock'])>
                Apenas zerados
            </label>

            <label>
                <input type="checkbox" name="only_stale" value="1" @checked($filters['only_stale'])>
                Apenas sem movimentacao recente
            </label>

            <label>
                <input type="checkbox" name="only_maintenance_consumption" value="1" @checked($filters['only_maintenance_consumption'])>
                Apenas consumo por manutencao
            </label>

            @if($context['can_view_cancelled'])
                <label>
                    <input type="checkbox" name="include_cancelled" value="1" @checked($filters['include_cancelled'])>
                    Cancelados em secao futura
                </label>
            @endif

            <button type="submit" class="report-module-button">Aplicar filtros</button>
        </div>

        <div class="tire-filter-note">
            Inventario e valor estimado sao atuais. Eventos, consumo e reversoes respeitam o periodo selecionado.
        </div>
    </form>

    @if($filters['period_error'])
        <div class="tire-report-alert">{{ $filters['period_error'] }}</div>
    @endif

    <section class="tire-report-section">
        <div class="tire-section-header">
            <div>
                <span class="tire-section-kicker">Resumo atual</span>
                <h2>Estoque da unidade</h2>
                <p>{{ $estimated_inventory_value_note }}</p>
            </div>
        </div>

        <div class="tire-summary-grid stock-summary-grid">
            <div class="tire-summary-card"><span>Itens ativos</span><strong>{{ $inventory_summary['active_items'] }}</strong></div>
            <div class="tire-summary-card warning"><span>Abaixo do minimo</span><strong>{{ $inventory_summary['low_stock'] }}</strong></div>
            <div class="tire-summary-card danger"><span>Zerados</span><strong>{{ $inventory_summary['zero_stock'] }}</strong></div>
            <div class="tire-summary-card"><span>Valor estimado do estoque</span><strong>{{ $money($estimated_inventory_value) }}</strong></div>
            <div class="tire-summary-card"><span>Entradas</span><strong>{{ $qty($total_entries_quantity) }}</strong></div>
            <div class="tire-summary-card"><span>Saidas manuais</span><strong>{{ $qty($total_outputs_quantity) }}</strong></div>
            <div class="tire-summary-card"><span>Consumo manutencao</span><strong>{{ $money($total_consumed_cost) }}</strong></div>
            <div class="tire-summary-card warning"><span>Reversoes</span><strong>{{ $reversal_movements->count() }}</strong></div>
            <div class="tire-summary-card warning"><span>Sem mov. recente</span><strong>{{ $stale_items->count() }}</strong></div>
            <div class="tire-summary-card"><span>Maior consumo</span><strong>{{ $topConsumed['item_name'] ?? '-' }}</strong></div>
        </div>
    </section>

    <div class="tire-report-columns stock-panel-columns">
        <section class="tire-report-section">
            <div class="tire-section-header">
                <div>
                    <h2>Itens criticos</h2>
                    <p>Top 5 itens zerados ou abaixo do minimo.</p>
                </div>
            </div>

            <div class="tire-compact-list">
                @forelse($zero_stock_items->merge($low_stock_items)->unique(fn ($row) => $row['item']->id)->take(5) as $row)
                    <div class="tire-compact-item danger">
                        <strong>{{ $row['item']->name }}</strong>
                        <span>{{ $qty($row['current_quantity']) }} {{ $row['unit'] }} | minimo {{ $qty($row['minimum_quantity']) }}</span>
                    </div>
                @empty
                    <div class="empty-state">Nenhum item critico na unidade ativa.</div>
                @endforelse
            </div>
        </section>

        <section class="tire-report-section">
            <div class="tire-section-header">
                <div>
                    <h2>Maior consumo</h2>
                    <p>Top 5 itens consumidos em manutencoes no periodo.</p>
                </div>
            </div>

            <div class="tire-compact-list">
                @forelse($top_consumed_items->take(5) as $row)
                    <div class="tire-compact-item">
                        <strong>{{ $row['item_name'] ?? '-' }}</strong>
                        <span>{{ $qty($row['quantity']) }} | {{ $money($row['total_cost']) }} @if($row['cost_has_estimates']) | estimado @endif</span>
                    </div>
                @empty
                    <div class="empty-state">Nenhum consumo por manutencao no periodo.</div>
                @endforelse
            </div>
        </section>
    </div>

    <div class="tire-report-columns stock-panel-columns">
        <section class="tire-report-section">
            <div class="tire-section-header">
                <div>
                    <h2>Ultimas movimentacoes</h2>
                    <p>At&eacute; 5 movimentos operacionais recentes.</p>
                </div>
            </div>

            <div class="tire-compact-list">
                @forelse($latest_movements->take(5) as $movement)
                    <div class="tire-compact-item">
                        <strong>{{ $movement['item_name'] ?? '-' }} - {{ $movement['classification_label'] }}</strong>
                        <span>{{ $formatDateTime($movement['date']) }} | {{ $qty($movement['quantity']) }} | {{ $money($movement['total_cost']) }}</span>
                    </div>
                @empty
                    <div class="empty-state">Nenhuma movimentacao registrada.</div>
                @endforelse
            </div>
        </section>

        <section class="tire-report-section">
            <div class="tire-section-header">
                <div>
                    <h2>Consumo por manutencao</h2>
                    <p>At&eacute; 5 consumos vinculados a manutencoes validas.</p>
                </div>
            </div>

            <div class="tire-compact-list">
                @forelse($maintenance_consumption->take(5) as $movement)
                    <div class="tire-compact-item">
                        <strong>{{ $movement['item_name'] ?? '-' }}</strong>
                        <span>
                            {{ $movement['vehicle']?->plate ?? '-' }}
                            | {{ $movement['procedure']?->name ?? 'Sem procedimento' }}
                            | {{ $qty($movement['quantity']) }}
                            | {{ $money($movement['total_cost']) }}
                        </span>
                    </div>
                @empty
                    <div class="empty-state">Nenhum consumo por manutencao no periodo.</div>
                @endforelse
            </div>
        </section>
    </div>
</div>
@endsection
