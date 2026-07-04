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
    $fuelFillingsList = collect($fuel_fillings);
    $fuelConsumptionList = collect($fuel_consumption ?? []);
    $fuelByProductList = collect($summary['fuel_by_product'] ?? []);
    $cancelledList = collect($cancelled_records);
    $consumptionStatusLabel = fn ($status) => match ($status) {
        'calculado' => 'Calculado',
        'dados_insuficientes' => 'Dados insuficientes',
        'km_invalido' => 'KM invalido',
        'horas_invalidas' => 'Horas invalidas',
        'sem_km_hr' => 'Sem KM/HR',
        default => $status ?: '-',
    };
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
                    <p>Custo de manutencao usa o valor registrado da ordem. Pecas e custos avulsos aparecem como detalhamento separado, sem nova soma.</p>
                </div>
            </div>

            <div class="tire-summary-grid dossier-summary-grid">
                <div class="tire-summary-card"><span>Manutencoes</span><strong>{{ $summary['maintenance_count'] }}</strong><small>Validas no periodo</small></div>
                <div class="tire-summary-card"><span>Custo registrado da ordem</span><strong>{{ $money($summary['maintenance_cost_registered']) }}</strong><small>Total oficial da manutencao</small></div>
                <div class="tire-summary-card"><span>Pecas consumidas</span><strong>{{ $money($summary['stock_consumed_cost']) }}</strong><small>{{ $summary['stock_consumed_cost_estimated'] > 0 ? 'Detalhe com estimativas' : 'Detalhamento separado' }}</small></div>
                <div class="tire-summary-card"><span>Abastecimentos</span><strong>{{ $summary['fuel_fillings_count'] }}</strong><small>Validos no periodo</small></div>
                <div class="tire-summary-card"><span>Litros abastecidos</span><strong>{{ $number($summary['fuel_liters'], 3) }}</strong><small>Diesel/ARLA separados abaixo</small></div>
                <div class="tire-summary-card"><span>Custo abastecimento</span><strong>{{ $money($summary['fuel_cost']) }}</strong><small>Custo registrado</small></div>
                <div class="tire-summary-card warning"><span>Sem KM/HR</span><strong>{{ $summary['fuel_fillings_without_km_hr'] }}</strong><small>Diagnostico de leitura</small></div>
                <div class="tire-summary-card"><span>Pneus instalados</span><strong>{{ $summary['installed_tires_count'] }}</strong><small>Em preparacao</small></div>
                <div class="tire-summary-card"><span>Medicoes de pneus</span><strong>{{ $summary['tire_measurements_count'] }}</strong><small>Em preparacao</small></div>
                <div class="tire-summary-card"><span>Operacoes</span><strong>{{ $summary['operations_count'] }}</strong><small>Em preparacao</small></div>
                <div class="tire-summary-card"><span>Checklists concluidos</span><strong>{{ $summary['checklists_completed_count'] }}</strong><small>Em preparacao</small></div>
                <div class="tire-summary-card warning"><span>Alertas</span><strong>{{ $summary['alerts_count'] }}</strong><small>Em preparacao</small></div>
                <div class="tire-summary-card danger"><span>Total operacional</span><strong>Em preparacao</strong><small>Nao soma pecas novamente</small></div>
            </div>
        </section>

        <section class="tire-report-section dossier-data-section">
            <div class="tire-section-header">
                <div>
                    <span class="tire-section-kicker">Abastecimentos</span>
                    <h2>Resumo por produto</h2>
                    <p>Diesel e ARLA separados. Cancelados nao entram em litros, custos ou consumo.</p>
                </div>
            </div>

            <div class="dossier-product-grid">
                @forelse($fuelByProductList as $product)
                    <div class="dossier-product-card">
                        <span>{{ $product['product_name'] }}</span>
                        <strong>{{ $number($product['liters'], 3) }} L</strong>
                        <small>{{ $product['fillings_count'] }} abastecimento(s) | {{ $money($product['total_cost']) }}</small>
                    </div>
                @empty
                    <div class="dossier-product-card is-empty">
                        <span>Sem abastecimentos</span>
                        <strong>0,000 L</strong>
                        <small>Nenhum registro valido no periodo.</small>
                    </div>
                @endforelse
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
                            <th>Custo registrado da ordem</th>
                            <th>Observacao</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($maintenancesList as $maintenance)
                            @php
                                $maintenanceItems = collect($maintenance['items'] ?? []);
                                $visibleItems = $maintenanceItems->take(3);
                                $hiddenItemsCount = max(0, $maintenanceItems->count() - $visibleItems->count());
                            @endphp
                            <tr>
                                <td>{{ $formatDate($maintenance['date']) }}</td>
                                <td>
                                    <strong>Ordem #{{ $maintenance['id'] }}</strong>
                                    <div class="dossier-muted-line">{{ $maintenance['procedure_summary'] }}</div>

                                    <div class="dossier-muted-line">
                                        {{ $maintenance['items_count'] }} serviço(s)
                                    </div>

                                    @foreach($visibleItems as $item)
                                        <div class="dossier-item-line">
                                            <strong>{{ $item['procedure_name'] }}</strong>
                                            @if($item['maintenance_type'])
                                                <span>{{ $item['maintenance_type'] }}</span>
                                            @endif

                                            @if($item['dynamic_values']->isNotEmpty())
                                                <div class="dossier-muted-line">
                                                    @foreach($item['dynamic_values']->take(3) as $dynamicValue)
                                                        <span>{{ $dynamicValue['label'] }}: {{ $dynamicValue['value'] }}</span>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach

                                    @if($hiddenItemsCount > 0)
                                        <div class="dossier-muted-line">+ {{ $hiddenItemsCount }} itens</div>
                                    @endif
                                </td>
                                <td>
                                    @if($maintenance['items_count'] > 1)
                                        Múltipla
                                    @else
                                        {{ $maintenance['maintenance_type'] ?: '-' }}
                                    @endif
                                </td>
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
                    <p>Consumos vinculados a manutencoes validas do veiculo. Detalhamento separado; nao somado novamente ao custo registrado da ordem.</p>
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

        <section class="tire-report-section dossier-data-section">
            <div class="tire-section-header">
                <div>
                    <span class="tire-section-kicker">Abastecimentos</span>
                    <h2>Abastecimentos no periodo</h2>
                    <p>Registros validos do veiculo na unidade ativa, com leituras e custos informados no lancamento.</p>
                </div>
            </div>

            <div class="dossier-table-wrap">
                <table class="dossier-table">
                    <thead>
                        <tr>
                            <th>Data/hora</th>
                            <th>Produto</th>
                            <th>Tanque</th>
                            <th>Motorista</th>
                            <th>KM/HR</th>
                            <th>Litros</th>
                            <th>Custo unit.</th>
                            <th>Custo total</th>
                            <th>Responsavel</th>
                            <th>Observacao</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($fuelFillingsList as $filling)
                            <tr @class(['has-warning' => $filling['vehicle_km'] === null && $filling['vehicle_hours'] === null])>
                                <td>{{ $filling['date'] ? \Carbon\Carbon::parse($filling['date'])->format('d/m/Y H:i') : '-' }}</td>
                                <td><strong>{{ $filling['product_name'] }}</strong></td>
                                <td>{{ $filling['tank_name'] }}</td>
                                <td>{{ $filling['driver_name'] }}</td>
                                <td>
                                    KM {{ $filling['vehicle_km'] !== null ? $number($filling['vehicle_km']) : '-' }}
                                    <br>
                                    HR {{ $filling['vehicle_hours'] !== null ? $number($filling['vehicle_hours'], 1) : '-' }}
                                    @if($filling['vehicle_km'] === null && $filling['vehicle_hours'] === null)
                                        <span class="dossier-badge warning">Sem KM/HR</span>
                                    @endif
                                </td>
                                <td>{{ $number($filling['quantity_liters'], 3) }} L</td>
                                <td>{{ $filling['unit_cost'] !== null ? $money($filling['unit_cost']) : '-' }}</td>
                                <td>{{ $money($filling['total_cost']) }}</td>
                                <td>{{ $filling['responsible_name'] }}</td>
                                <td>{{ $filling['notes'] ?: '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="dossier-empty-cell">Nenhum abastecimento valido encontrado para o periodo.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="tire-report-section dossier-data-section">
            <div class="tire-section-header">
                <div>
                    <span class="tire-section-kicker">Consumo</span>
                    <h2>Consumo e leitura operacional</h2>
                    <p>Calculo conservador: exige pelo menos duas leituras crescentes por produto. Medias nao confiaveis nao sao exibidas.</p>
                </div>
            </div>

            <div class="dossier-table-wrap">
                <table class="dossier-table dossier-consumption-table">
                    <thead>
                        <tr>
                            <th>Produto</th>
                            <th>Registros</th>
                            <th>Litros</th>
                            <th>Custo</th>
                            <th>KM inicial/final</th>
                            <th>km/L</th>
                            <th>HR inicial/final</th>
                            <th>L/h</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($fuelConsumptionList as $consumption)
                            <tr @class(['has-warning' => $consumption['status'] !== 'calculado'])>
                                <td><strong>{{ $consumption['product_name'] }}</strong></td>
                                <td>{{ $consumption['fillings_count'] }}</td>
                                <td>{{ $number($consumption['total_liters'], 3) }} L</td>
                                <td>{{ $money($consumption['total_cost']) }}</td>
                                <td>
                                    {{ $consumption['km_consumption']['initial'] !== null ? $number($consumption['km_consumption']['initial']) : '-' }}
                                    /
                                    {{ $consumption['km_consumption']['final'] !== null ? $number($consumption['km_consumption']['final']) : '-' }}
                                </td>
                                <td>
                                    {{ $consumption['km_consumption']['value'] !== null ? $number($consumption['km_consumption']['value'], 3) : '-' }}
                                    <div class="dossier-muted-line">{{ $consumptionStatusLabel($consumption['km_consumption']['status']) }}</div>
                                </td>
                                <td>
                                    {{ $consumption['hours_consumption']['initial'] !== null ? $number($consumption['hours_consumption']['initial'], 1) : '-' }}
                                    /
                                    {{ $consumption['hours_consumption']['final'] !== null ? $number($consumption['hours_consumption']['final'], 1) : '-' }}
                                </td>
                                <td>
                                    {{ $consumption['hours_consumption']['value'] !== null ? $number($consumption['hours_consumption']['value'], 3) : '-' }}
                                    <div class="dossier-muted-line">{{ $consumptionStatusLabel($consumption['hours_consumption']['status']) }}</div>
                                </td>
                                <td><span @class(['dossier-badge', 'warning' => $consumption['status'] !== 'calculado'])>{{ $consumptionStatusLabel($consumption['status']) }}</span></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="dossier-empty-cell">Sem base de abastecimento para diagnostico de consumo no periodo.</td>
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
                        <h2>Registros cancelados</h2>
                        <p>Manutencoes e abastecimentos cancelados exibidos separadamente. Nao entram em contagem, litros, custo ou resumo operacional.</p>
                    </div>
                </div>

                <div class="dossier-table-wrap">
                    <table class="dossier-table">
                        <thead>
                            <tr>
                                <th>Tipo</th>
                                <th>Data</th>
                                <th>Registro</th>
                                <th>Qtd./Valor</th>
                                <th>Cancelada em</th>
                                <th>Responsavel</th>
                                <th>Motivo</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($cancelledList as $cancelled)
                                <tr class="is-muted">
                                    <td>{{ $cancelled['record_type'] ?? 'Registro cancelado' }}</td>
                                    <td>{{ $formatDate($cancelled['date']) }}</td>
                                    <td>{{ $cancelled['record_label'] ?? $cancelled['procedure_name'] ?? '-' }}</td>
                                    <td>
                                        @if(($cancelled['quantity_liters'] ?? null) !== null)
                                            {{ $number($cancelled['quantity_liters'], 3) }} L
                                            <br>
                                        @endif
                                        {{ $money($cancelled['total_cost'] ?? 0) }}
                                    </td>
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
                    <h2>Politica oficial do custo de manutencao</h2>
                    <p>
                        <strong>maintenance_records.total_cost</strong> e a fonte oficial do custo operacional consolidado da ordem.
                        Itens, avulsos e pecas sao trilhas de composicao e nao entram em nova soma.
                    </p>
                </div>
            </div>

            <div class="dossier-policy-grid">
                <div><span>Politica da ordem</span><strong>{{ $cost_policy['maintenance_total_includes_stock'] }}</strong></div>
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
