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
    $maintenancesList = collect($maintenances);
    $stockConsumptionList = collect($stock_consumption);
    $cancelledList = collect($cancelled_records);
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
                    <p>Manutencoes e pecas ja consolidadas; total operacional definitivo segue pendente para evitar duplicidade.</p>
                </div>
            </div>

            <div class="tire-summary-grid dossier-summary-grid">
                <div class="tire-summary-card"><span>Manutencoes</span><strong>{{ $summary['maintenance_count'] }}</strong><small>Validas no periodo</small></div>
                <div class="tire-summary-card"><span>Custo manutencao</span><strong>{{ $money($summary['maintenance_cost_registered']) }}</strong><small>Custo registrado</small></div>
                <div class="tire-summary-card"><span>Pecas consumidas</span><strong>{{ $money($summary['stock_consumed_cost']) }}</strong><small>{{ $summary['stock_consumed_cost_estimated'] > 0 ? 'Contem estimativas' : 'Estoque vinculado' }}</small></div>
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

        <section class="tire-report-section dossier-data-section">
            <div class="tire-section-header">
                <div>
                    <span class="tire-section-kicker">Manutencoes</span>
                    <h2>Manutencoes no periodo</h2>
                    <p>Somente registros validos, sem manutencoes canceladas.</p>
                </div>
            </div>

            <div class="dossier-table-wrap">
                <table class="dossier-table">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Procedimento</th>
                            <th>Tipo</th>
                            <th>Fornecedor/oficina</th>
                            <th>KM/HR</th>
                            <th>Custo registrado</th>
                            <th>Observacao</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($maintenancesList as $maintenance)
                            <tr>
                                <td>{{ $formatDate($maintenance['date']) }}</td>
                                <td>
                                    <strong>{{ $maintenance['procedure_name'] }}</strong>
                                    @if($maintenance['dynamic_values']->isNotEmpty())
                                        <div class="dossier-muted-line">
                                            @foreach($maintenance['dynamic_values'] as $dynamicValue)
                                                <span>{{ $dynamicValue['label'] }}: {{ $dynamicValue['value'] }}</span>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                                <td>{{ $maintenance['maintenance_type'] ?: '-' }}</td>
                                <td>{{ $maintenance['provider_name'] ?: '-' }}</td>
                                <td>
                                    KM {{ $maintenance['performed_km'] !== null ? $number($maintenance['performed_km']) : '-' }}
                                    <br>
                                    HR {{ $maintenance['performed_hours'] !== null ? $number($maintenance['performed_hours'], 1) : '-' }}
                                </td>
                                <td>{{ $money($maintenance['total_cost']) }}</td>
                                <td>{{ $maintenance['notes'] ?: $maintenance['reason'] ?: '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="dossier-empty-cell">Nenhuma manutencao valida encontrada para o periodo.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="tire-report-section dossier-data-section">
            <div class="tire-section-header">
                <div>
                    <span class="tire-section-kicker">Estoque consumido</span>
                    <h2>Pecas consumidas do estoque</h2>
                    <p>Consumos vinculados a manutencoes validas do veiculo. Reversoes e cancelados ficam fora desta tabela.</p>
                </div>
            </div>

            <div class="dossier-table-wrap">
                <table class="dossier-table">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Manutencao</th>
                            <th>Item</th>
                            <th>Categoria</th>
                            <th>Qtd.</th>
                            <th>Custo unit.</th>
                            <th>Custo total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($stockConsumptionList as $movement)
                            <tr>
                                <td>{{ $formatDate($movement['date']) }}</td>
                                <td>
                                    #{{ $movement['maintenance_id'] }}
                                    <div class="dossier-muted-line">{{ $movement['procedure_name'] }}</div>
                                </td>
                                <td>{{ $movement['item_name'] }}</td>
                                <td>{{ $movement['category_name'] }}</td>
                                <td>{{ $number($movement['quantity'], 2) }}</td>
                                <td>
                                    {{ $money($movement['unit_cost']) }}
                                    @if($movement['cost_is_estimated'])
                                        <span class="dossier-badge warning">Estimado</span>
                                    @endif
                                </td>
                                <td>{{ $money($movement['total_cost']) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="dossier-empty-cell">Nenhum consumo de estoque vinculado a manutencao no periodo.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        @if($cancelledList->isNotEmpty())
            <section class="tire-report-section dossier-data-section dossier-cancelled-section">
                <div class="tire-section-header">
                    <div>
                        <span class="tire-section-kicker">Canceladas</span>
                        <h2>Manutencoes canceladas</h2>
                        <p>Registros exibidos separadamente. Nao entram em contagem, custo ou resumo operacional.</p>
                    </div>
                </div>

                <div class="dossier-table-wrap">
                    <table class="dossier-table">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Procedimento</th>
                                <th>Custo registrado</th>
                                <th>Cancelada em</th>
                                <th>Responsavel</th>
                                <th>Motivo</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($cancelledList as $cancelled)
                                <tr class="is-muted">
                                    <td>{{ $formatDate($cancelled['date']) }}</td>
                                    <td>{{ $cancelled['procedure_name'] }}</td>
                                    <td>{{ $money($cancelled['total_cost']) }}</td>
                                    <td>{{ $cancelled['cancelled_at'] ? \Carbon\Carbon::parse($cancelled['cancelled_at'])->format('d/m/Y H:i') : '-' }}</td>
                                    <td>{{ $cancelled['cancelled_by'] ?: '-' }}</td>
                                    <td>{{ $cancelled['cancel_reason'] ?: '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

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
