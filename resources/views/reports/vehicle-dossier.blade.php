@extends('layouts.app')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/pages/reports.css') }}?v=6">
@endpush

@section('content')
@php
    $filters = $applied_filters;
    $formatDate = fn ($date) => $date ? \Carbon\Carbon::parse($date)->format('d/m/Y') : '-';
    $number = fn ($value, $decimals = 0) => $value !== null ? number_format((float) $value, $decimals, ',', '.') : '-';
    $money = fn ($value) => $value !== null ? 'R$ ' . number_format((float) $value, 2, ',', '.') : 'Em preparacao';
    $hasAnyFilter = request()->filled('vehicle_id') || request()->filled('start_date') || request()->filled('end_date');
    $isValid = $validation['is_valid'] ?? false;
    $summary = $executive_summary;
    $statusLabel = fn ($value) => $value ?: '-';
@endphp

<div class="reports-page reports-tires-page reports-vehicle-dossier-page tire-summary-dashboard">
    <div class="reports-hero reports-tires-hero">
        <div>
            <div class="reports-hero-badge">Prontuario operacional</div>
            <h1>Dossie Individual do Veiculo</h1>
            <p>
                Consulta estrutural por veiculo e periodo, respeitando
                {{ $context['location']->name ?? 'a unidade ativa' }}.
            </p>
        </div>
    </div>

    <form method="GET" action="{{ route('reports.vehicle-dossier.index') }}" class="tire-report-filters dossier-filter-form">
        <div class="tire-filter-grid dossier-filter-grid">
            <label>
                Veiculo
                <select name="vehicle_id" required>
                    <option value="">Selecione um veiculo</option>
                    @foreach($vehicles as $option)
                        <option value="{{ $option->id }}" @selected((int) $filters['vehicle_id'] === (int) $option->id)>
                            {{ $option->name }} - {{ $option->plate }}
                            @if($option->asset_code)
                                | {{ $option->asset_code }}
                            @endif
                        </option>
                    @endforeach
                </select>
            </label>

            <label>
                Data inicial
                <input
                    type="date"
                    name="start_date"
                    value="{{ $filters['start_date'] ? $filters['start_date']->toDateString() : '' }}"
                    required
                >
            </label>

            <label>
                Data final
                <input
                    type="date"
                    name="end_date"
                    value="{{ $filters['end_date'] ? $filters['end_date']->toDateString() : '' }}"
                    required
                >
            </label>
        </div>

        <div class="tire-filter-checks dossier-filter-checks">
            @if($context['can_view_cancelled'])
                <label>
                    <input type="checkbox" name="include_cancelled" value="1" @checked($filters['include_cancelled'])>
                    Incluir cancelados em secao separada
                </label>

                <label>
                    <input type="checkbox" name="include_audit" value="1" @checked($filters['include_audit'])>
                    Incluir auditoria quando disponivel
                </label>
            @endif

            <label>
                <input type="checkbox" name="include_fillings_without_km_hr" value="1" @checked($filters['include_fillings_without_km_hr'])>
                Diagnosticar abastecimentos sem KM/HR
            </label>

            <button type="submit" class="report-module-button">Gerar dossie</button>
        </div>

        <div class="tire-filter-note">
            Veiculo e periodo sao obrigatorios. O dossie nunca mistura dados de outra unidade.
        </div>
    </form>

    @if(! $hasAnyFilter)
        <section class="tire-report-section dossier-empty-guidance">
            <div class="tire-section-header">
                <div>
                    <span class="tire-section-kicker">Comece por aqui</span>
                    <h2>Selecione um veiculo e um periodo para gerar o dossie.</h2>
                    <p>
                        Esta primeira versao prepara o prontuario operacional.
                        As secoes detalhadas de manutencao, pneus, abastecimentos,
                        operacoes e checklists entram nos proximos blocos.
                    </p>
                </div>
            </div>
        </section>
    @elseif(! $isValid)
        <section class="tire-report-section dossier-validation-panel">
            <div class="tire-section-header">
                <div>
                    <span class="tire-section-kicker">Validacao</span>
                    <h2>Revise os filtros para gerar o dossie</h2>
                    <p>Nenhuma informacao operacional foi consolidada com filtros invalidos.</p>
                </div>
            </div>

            <div class="dossier-alert-list">
                @foreach($validation['errors'] as $error)
                    <div class="tire-report-alert">{{ $error }}</div>
                @endforeach
            </div>
        </section>
    @else
        <section class="tire-report-section dossier-vehicle-header">
            <div class="tire-section-header">
                <div>
                    <span class="tire-section-kicker">Cabecalho do veiculo</span>
                    <h2>{{ $vehicle['name'] }} - {{ $vehicle['plate'] }}</h2>
                    <p>
                        {{ $vehicle['brand'] ?: '-' }} / {{ $vehicle['vehicle_model'] ?: '-' }}
                        @if($vehicle['year'])
                            | {{ $vehicle['year'] }}
                        @endif
                    </p>
                </div>
            </div>

            <div class="dossier-vehicle-grid">
                <div><span>Tipo</span><strong>{{ $vehicle['type'] ?: '-' }}</strong></div>
                <div><span>Codigo patrimonial</span><strong>{{ $vehicle['asset_code'] ?: '-' }}</strong></div>
                <div><span>Status</span><strong>{{ $statusLabel($vehicle['status']) }}</strong></div>
                <div><span>Status operacional</span><strong>{{ $statusLabel($vehicle['operational_status']) }}</strong></div>
                <div><span>Unidade</span><strong>{{ $vehicle['location']?->name ?? '-' }}</strong></div>
                <div><span>Divisao</span><strong>{{ $vehicle['division']?->name ?? '-' }}</strong></div>
                <div><span>KM atual</span><strong>{{ $number($vehicle['current_km'], 0) }}</strong></div>
                <div><span>Horimetro atual</span><strong>{{ $number($vehicle['current_hours'], 1) }}</strong></div>
            </div>
        </section>

        <section class="tire-report-section">
            <div class="tire-section-header">
                <div>
                    <span class="tire-section-kicker">Resumo executivo</span>
                    <h2>Estrutura inicial do dossie</h2>
                    <p>Campos preparados para os proximos blocos; valores consolidados ainda nao sao definitivos.</p>
                </div>
            </div>

            <div class="tire-summary-grid dossier-summary-grid">
                <div class="tire-summary-card"><span>Manutencoes</span><strong>{{ $summary['maintenance_count'] }}</strong><small>Em preparacao</small></div>
                <div class="tire-summary-card"><span>Custo manutencao</span><strong>{{ $money($summary['maintenance_cost']) }}</strong><small>Nao consolidado</small></div>
                <div class="tire-summary-card"><span>Pecas consumidas</span><strong>{{ $summary['stock_consumed_cost'] > 0 ? $money($summary['stock_consumed_cost']) : 'Em preparacao' }}</strong><small>Estoque vinculado</small></div>
                <div class="tire-summary-card"><span>Litros abastecidos</span><strong>{{ $summary['fuel_liters'] > 0 ? $number($summary['fuel_liters'], 2) : 'Em preparacao' }}</strong><small>Sem calculo km/L</small></div>
                <div class="tire-summary-card"><span>Custo abastecimento</span><strong>{{ $summary['fuel_cost'] > 0 ? $money($summary['fuel_cost']) : 'Em preparacao' }}</strong><small>Nao consolidado</small></div>
                <div class="tire-summary-card"><span>Pneus instalados</span><strong>{{ $summary['installed_tires_count'] }}</strong><small>Em preparacao</small></div>
                <div class="tire-summary-card"><span>Medicoes de pneus</span><strong>{{ $summary['tire_measurements_count'] }}</strong><small>Em preparacao</small></div>
                <div class="tire-summary-card"><span>Operacoes</span><strong>{{ $summary['operations_count'] }}</strong><small>Em preparacao</small></div>
                <div class="tire-summary-card"><span>Checklists concluidos</span><strong>{{ $summary['checklists_completed_count'] }}</strong><small>Em preparacao</small></div>
                <div class="tire-summary-card warning"><span>Alertas</span><strong>{{ $summary['alerts_count'] }}</strong><small>Em preparacao</small></div>
                <div class="tire-summary-card danger"><span>Total operacional</span><strong>Em preparacao</strong><small>Nao definitivo</small></div>
            </div>
        </section>

        <section class="tire-report-section dossier-policy-section">
            <div class="tire-section-header">
                <div>
                    <span class="tire-section-kicker">Politica de custos</span>
                    <h2>Total operacional ainda nao definido</h2>
                    <p>
                        Antes de somar manutencao e pecas, precisamos confirmar se
                        <strong>maintenance_records.total_cost</strong> ja incorpora itens de estoque.
                    </p>
                </div>
            </div>

            <div class="dossier-policy-grid">
                <div><span>Manutencao inclui estoque?</span><strong>{{ $cost_policy['maintenance_total_includes_stock'] }}</strong></div>
                <div><span>Fonte manutencao</span><strong>{{ $cost_policy['maintenance_cost_source'] }}</strong></div>
                <div><span>Fonte estoque</span><strong>{{ $cost_policy['stock_cost_source'] }}</strong></div>
                <div><span>Regra do total</span><strong>{{ $cost_policy['operational_total_rule'] }}</strong></div>
            </div>

            <div class="dossier-warning-list">
                @foreach($cost_policy['warnings'] as $warning)
                    <div class="tire-compact-item warning">
                        <strong>Atencao</strong>
                        <span>{{ $warning }}</span>
                    </div>
                @endforeach
            </div>
        </section>
    @endif
</div>
@endsection
