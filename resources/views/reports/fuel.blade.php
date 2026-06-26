@extends('layouts.app')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/pages/reports.css') }}?v=6">
@endpush

@section('content')
@php
    $filters = $applied_filters;
    $formatDate = fn ($date) => $date ? \Carbon\Carbon::parse($date)->format('d/m/Y') : '-';
    $liters = fn ($value) => number_format((float) $value, 1, ',', '.') . ' L';
    $money = fn ($value) => 'R$ ' . number_format((float) $value, 2, ',', '.');
    $diesel = $product_balances->first(fn ($item) => str_contains(mb_strtolower(($item['slug'] ?: $item['name'])), 'diesel'));
    $arla = $product_balances->first(fn ($item) => str_contains(mb_strtolower(($item['slug'] ?: $item['name'])), 'arla'));
@endphp

<div class="reports-page reports-tires-page reports-fuel-page tire-summary-dashboard">
    <div class="reports-hero reports-tires-hero">
        <div>
            <div class="reports-hero-badge">Central de Abastecimentos</div>
            <h1>Painel de Abastecimentos</h1>
            <p>
                Resumo operacional de tanques, recebimentos e abastecimentos em
                {{ $context['location']->name ?? 'unidade ativa' }}.
            </p>
        </div>

        <div class="tire-action-row">
            <a href="{{ route('reports.fuel.full', request()->query()) }}" class="report-module-button">
                Visualizar relatorio completo
            </a>
            <a href="{{ route('reports.fuel.export-pdf', request()->query()) }}" class="report-module-button secondary">
                Exportar PDF
            </a>
            <a href="{{ route('reports.fuel.export-excel', request()->query()) }}" class="report-module-button secondary">
                Exportar Excel
            </a>
        </div>
    </div>

    <form method="GET" action="{{ route('reports.fuel.index') }}" class="tire-report-filters">
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
                Produto
                <select name="fuel_product_id">
                    <option value="">Todos os produtos</option>
                    @foreach($products as $product)
                        <option value="{{ $product->id }}" @selected((int) $filters['fuel_product_id'] === (int) $product->id)>
                            {{ $product->name }}
                        </option>
                    @endforeach
                </select>
            </label>

            <label>
                Tanque
                <select name="fuel_tank_id">
                    <option value="">Todos os tanques</option>
                    @foreach($tanks as $tank)
                        <option value="{{ $tank->id }}" @selected((int) $filters['fuel_tank_id'] === (int) $tank->id)>
                            {{ $tank->name }} - {{ $tank->product?->name }}
                        </option>
                    @endforeach
                </select>
            </label>
        </div>

        <div class="tire-filter-checks">
            <label>
                <input type="checkbox" name="only_low_tanks" value="1" @checked($filters['only_low_tanks'])>
                Apenas tanque baixo
            </label>

            <label>
                <input type="checkbox" name="only_vehicles_with_consumption" value="1" @checked($filters['only_vehicles_with_consumption'])>
                Apenas veiculos com consumo
            </label>

            <label>
                <input type="checkbox" name="include_missing_counters" value="1" @checked($filters['include_missing_counters'])>
                Diagnosticar sem KM/HR
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
            Saldos dos tanques sao atuais. Recebimentos, abastecimentos, custos e consumo respeitam o periodo selecionado.
        </div>
    </form>

    @if($filters['period_error'])
        <div class="tire-report-alert">{{ $filters['period_error'] }}</div>
    @endif

    <section class="tire-report-section">
        <div class="tire-section-header">
            <div>
                <span class="tire-section-kicker">Resumo atual</span>
                <h2>Saldos e movimentacao</h2>
                <p>Cancelados nao entram em litros, custos, saldos, medias ou rankings.</p>
            </div>
        </div>

        <div class="tire-summary-grid fuel-summary-grid">
            <div class="tire-summary-card"><span>Saldo Diesel</span><strong>{{ $liters($diesel['balance_liters'] ?? 0) }}</strong></div>
            <div class="tire-summary-card"><span>Saldo ARLA</span><strong>{{ $liters($arla['balance_liters'] ?? 0) }}</strong></div>
            <div class="tire-summary-card"><span>Tanques ativos</span><strong>{{ $tank_summary->filter(fn ($tank) => $tank['tank']->active)->count() }}</strong></div>
            <div class="tire-summary-card warning"><span>Tanques baixos</span><strong>{{ $low_tanks->count() }}</strong></div>
            <div class="tire-summary-card"><span>Recebido</span><strong>{{ $liters($total_received_liters) }}</strong></div>
            <div class="tire-summary-card"><span>Abastecido</span><strong>{{ $liters($total_filled_liters) }}</strong></div>
            <div class="tire-summary-card"><span>Custo abastecido</span><strong>{{ $money($total_filled_cost) }}</strong></div>
            <div class="tire-summary-card"><span>Custo medio/L</span><strong>{{ $average_cost_per_liter !== null ? $money($average_cost_per_liter) : '-' }}</strong></div>
            <div class="tire-summary-card"><span>Veiculos abastecidos</span><strong>{{ $vehicles_filled_count }}</strong></div>
            <div class="tire-summary-card danger"><span>Sem KM/HR</span><strong>{{ $fillings_without_km_hr->count() }}</strong></div>
        </div>
    </section>

    <div class="tire-report-columns fuel-panel-columns">
        <section class="tire-report-section">
            <div class="tire-section-header">
                <div>
                    <h2>Tanques em alerta</h2>
                    <p>Até 5 tanques abaixo do saldo minimo.</p>
                </div>
            </div>

            <div class="tire-compact-list">
                @forelse($low_tanks->take(5) as $item)
                    <div class="tire-compact-item danger">
                        <strong>{{ $item['tank']->name }} - {{ $item['product']?->name }}</strong>
                        <span>{{ $liters($item['current_balance_liters']) }} de {{ $liters($item['capacity_liters']) }} | minimo {{ $liters($item['minimum_balance_liters']) }}</span>
                    </div>
                @empty
                    <div class="empty-state">Nenhum tanque abaixo do minimo na unidade ativa.</div>
                @endforelse
            </div>
        </section>

        <section class="tire-report-section">
            <div class="tire-section-header">
                <div>
                    <h2>Maior consumo por veiculo</h2>
                    <p>Até 5 veiculos por litros abastecidos no periodo.</p>
                </div>
            </div>

            <div class="tire-compact-list">
                @forelse($top_consumption_vehicles as $row)
                    <div class="tire-compact-item">
                        <strong>{{ $row['vehicle']?->name ?? '-' }} - {{ $row['vehicle']?->plate ?? '-' }}</strong>
                        <span>
                            {{ $row['product']?->name ?? '-' }} | {{ $liters($row['total_liters']) }} | {{ $money($row['total_cost']) }}
                            @if($row['km_consumption']['status'] === 'calculado')
                                | {{ number_format($row['km_consumption']['value'], 2, ',', '.') }} km/L
                            @elseif($row['hours_consumption']['status'] === 'calculado')
                                | {{ number_format($row['hours_consumption']['value'], 2, ',', '.') }} L/h
                            @else
                                | Dados insuficientes
                            @endif
                        </span>
                    </div>
                @empty
                    <div class="empty-state">Nenhum abastecimento de veiculo no periodo.</div>
                @endforelse
            </div>
        </section>
    </div>

    <div class="tire-report-columns fuel-panel-columns">
        <section class="tire-report-section">
            <div class="tire-section-header">
                <div>
                    <h2>Ultimos recebimentos</h2>
                    <p>Até 5 entradas de combustível/ARLA.</p>
                </div>
            </div>

            <div class="tire-compact-list">
                @forelse($latest_receipts as $receipt)
                    <div class="tire-compact-item">
                        <strong>{{ $receipt->tank?->name ?? '-' }} - {{ $receipt->product?->name ?? '-' }}</strong>
                        <span>{{ $formatDate($receipt->received_at) }} | {{ $liters($receipt->quantity_liters) }} | {{ $money($receipt->total_cost) }}</span>
                    </div>
                @empty
                    <div class="empty-state">Nenhum recebimento registrado.</div>
                @endforelse
            </div>
        </section>

        <section class="tire-report-section">
            <div class="tire-section-header">
                <div>
                    <h2>Ultimos abastecimentos</h2>
                    <p>Até 5 abastecimentos de veiculos.</p>
                </div>
            </div>

            <div class="tire-compact-list">
                @forelse($latest_fillings as $filling)
                    <div class="tire-compact-item">
                        <strong>{{ $filling->vehicle?->name ?? '-' }} - {{ $filling->vehicle?->plate ?? '-' }}</strong>
                        <span>{{ $formatDate($filling->filled_at) }} | {{ $filling->product?->name ?? '-' }} | {{ $liters($filling->quantity_liters) }} | {{ $money($filling->total_cost) }}</span>
                    </div>
                @empty
                    <div class="empty-state">Nenhum abastecimento registrado.</div>
                @endforelse
            </div>
        </section>
    </div>
</div>
@endsection
