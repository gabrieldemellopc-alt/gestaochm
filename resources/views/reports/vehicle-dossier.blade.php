@extends('layouts.app')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/pages/reports.css') }}?v=6">
@endpush

@section('content')
@php
    $reportPermissions = $reportPermissions ?? [];
    $canExportReportPdf = $reportPermissions['reports.export_pdf'] ?? true;
    $canExportReportExcel = $reportPermissions['reports.export_excel'] ?? true;
@endphp
@php
    $filters = $applied_filters;
    $formatDate = fn ($date) => $date ? \Carbon\Carbon::parse($date)->format('d/m/Y') : '-';
    $number = fn ($value, $decimals = 0) => $value !== null ? number_format((float) $value, $decimals, ',', '.') : '-';
    $money = fn ($value) => $value !== null ? 'R$ ' . number_format((float) $value, 2, ',', '.') : 'Em preparacao';
    $hasAnyFilter = request()->filled('vehicle_id') || request()->filled('start_date') || request()->filled('end_date');
    $isValid = $validation['is_valid'] ?? false;
    $summary = $executive_summary;
    $label = fn (string $domain, $value, ?string $fallback = null) => \App\Support\ChmLabel::for($domain, $value, $fallback);
    $statusLabel = fn ($value) => $label('operational_status', $value);
    $maintenancesList = collect($maintenances);
    $stockConsumptionList = collect($stock_consumption);
    $fuelFillingsList = collect($fuel_fillings);
    $fuelConsumptionList = collect($fuel_consumption ?? []);
    $fuelByProductList = collect($summary['fuel_by_product'] ?? []);
    $tiresCurrent = collect($tires_current ?? []);
    $tireEvents = collect($tire_events ?? []);
    $tireMeasurements = collect($tire_measurements ?? []);
    $kmHrLogs = collect($km_hr_logs ?? []);
    $cancelledList = collect($cancelled_records);
    $selectedSections = collect($filters['sections'] ?? []);
    $sectionConfigEnabled = (bool) ($filters['section_config'] ?? false);
    $showSection = fn (string $section) => ! $sectionConfigEnabled || $selectedSections->contains($section);
    $consumptionStatusLabel = fn ($status) => $label('fuel_consumption_status', $status);
    $maintenanceTypeLabel = fn ($value) => $label('maintenance_type', $value);
    $observationLabel = fn ($value) => \App\Support\ChmLabel::knownToken([
        'maintenance_type',
        'service_type',
        'execution_type',
        'workflow_status',
    ], $value);
@endphp

<div class="reports-page reports-tires-page reports-vehicle-dossier-page tire-summary-dashboard">
    <div class="reports-hero reports-tires-hero dossier-hero">
        <div>
            <div class="reports-hero-badge">Prontuário operacional</div>
    
            <h1>Dossiê Individual do Veículo</h1>
    
            <p>
                Consulta estrutural por veículo e período, respeitando
                {{ $context['location']->name ?? 'a unidade ativa' }}.
            </p>
        </div>
    
        <div class="dossier-hero-actions">
            <a
                href="{{ route('vehicles.index') }}"
                class="dossier-hero-button secondary"
            >
                <i data-lucide="arrow-left"></i>
                Voltar para veículos
            </a>
    
            @if($isValid && $vehicle)
@if($canExportReportPdf)
<a
                    href="{{ route('reports.vehicle-dossier.pdf', request()->query()) }}"
                    class="dossier-hero-button primary"
                    target="_blank"
                    rel="noopener"
                >
                    <i data-lucide="file-text"></i>
                    Gerar PDF
                </a>
@endif
            @endif
        </div>
    </div>

    <form method="GET" action="{{ route('reports.vehicle-dossier.index') }}" class="tire-report-filters dossier-filter-form">
        <div class="tire-filter-grid dossier-filter-grid">
            <label>
                Veiculo
                <select name="vehicle_id" required>
                    <option value="">Selecione um veículo</option>
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
                    Incluir cancelados em seção separada
                </label>

                <label>
                    <input type="checkbox" name="include_audit" value="1" @checked($filters['include_audit'])>
                    Incluir auditoria quando disponível
                </label>
            @endif

            <label>
                <input type="checkbox" name="include_fillings_without_km_hr" value="1" @checked($filters['include_fillings_without_km_hr'])>
                Diagnosticar abastecimentos sem KM/HR
            </label>

            <button type="submit" class="report-module-button">Gerar dossiê</button>
        </div>

    </form>

    @if(! $hasAnyFilter)
        <section class="tire-report-section dossier-empty-guidance">
            <div class="tire-section-header">
                <div>
                    <span class="tire-section-kicker">Comece por aqui</span>
                    <h2>Selecione um veículo e um período para gerar o dossiê.</h2>
                    <p>
                        Informe o veículo e o período desejado para consultar o histórico operacional consolidado.
                    </p>
                </div>
            </div>
        </section>
    @elseif(! $isValid)
        <section class="tire-report-section dossier-validation-panel">
            <div class="tire-section-header">
                <div>
                    <span class="tire-section-kicker">Validação</span>
                    <h2>Revise os filtros para gerar o dossiê</h2>
                    <p>Nenhuma informação operacional foi consolidada com filtros inválidos.</p>
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
                    <span class="tire-section-kicker">IDENTIFICAÇÃO DO VEÍCULO</span>
                    <h2>{{ $vehicle['name'] }}</h2>
                    <p>
                        <strong>{{ $vehicle['plate'] }}</strong>
                    
                        <span class="dossier-separator">•</span>
                    
                        {{ $vehicle['brand'] }}
                    
                        <span class="dossier-separator">•</span>
                    
                        {{ $vehicle['vehicle_model'] }}
                    
                        <span class="dossier-separator">•</span>
                    
                        {{ $vehicle['year'] }}
                    </p>
                </div>
            </div>
        
            <div class="dossier-vehicle-grid">
                <div><span>Placa</span><strong>{{ $vehicle['plate'] }}</strong></div>
                <div><span>Código patrimonial</span><strong>{{ $vehicle['asset_code'] ?: '-' }}</strong></div>
                <div><span>Status</span><strong>{{ $statusLabel($vehicle['status']) }}</strong></div>
                <div><span>Status operacional</span><strong>{{ $statusLabel($vehicle['operational_status']) }}</strong></div>
        
                <div><span>KM atual</span><strong>{{ $number($vehicle['current_km'], 0) }}</strong></div>
                <div><span>Horímetro atual</span><strong>{{ $number($vehicle['current_hours'], 1) }}</strong></div>
                <div><span>Unidade</span><strong>{{ $vehicle['location']?->name ?? '-' }}</strong></div>
                <div><span>Divisão</span><strong>{{ $vehicle['division']?->name ?? '-' }}</strong></div>
        
            </div>
        </section>
        @if($showSection('summary') || $showSection('maintenance_costs'))
        <details open class="tire-report-section dossier-collapsible">
            <summary>
                <div>
                    <span class="tire-section-kicker">Resumo executivo</span>
                    <h2>Resumo do período</h2>
                    <p>Consolidação dos principais registros operacionais do veículo no período selecionado.</p>
                </div>
        
                <span class="dossier-collapse-indicator"></span>
            </summary>
        
            <div class="dossier-collapsible-body">
                <p class="dossier-cost-note">
                    Custos de peças são exibidos como detalhamento e não são somados novamente ao custo registrado da ordem.
                </p>
    
                <div class="dossier-summary-groups">
                    <div class="dossier-summary-group">
                        <h3 class="dossier-summary-title">Manutenção</h3>
                
                        <div class="tire-summary-grid dossier-summary-grid">
                            <div class="tire-summary-card">
                                <span>Manutenções</span>
                                <strong>{{ $summary['maintenance_count'] }}</strong>
                                <small>Válidas no período</small>
                            </div>
                
                            <div class="tire-summary-card">
                                <span>Custo registrado da ordem</span>
                                <strong>{{ $money($summary['maintenance_cost_registered']) }}</strong>
                                <small>Total oficial da manutenção</small>
                            </div>
                
                            <div class="tire-summary-card">
                                <span>Peças consumidas</span>
                                <strong>{{ $money($summary['stock_consumed_cost']) }}</strong>
                                <small>{{ $summary['stock_consumed_cost_estimated'] > 0 ? 'Detalhe com estimativas' : 'Detalhamento separado' }}</small>
                            </div>
                        </div>
                    </div>
                
                    <div class="dossier-summary-group">
                        <h3 class="dossier-summary-title">Combustível</h3>
                
                        <div class="tire-summary-grid dossier-summary-grid">
                            <div class="tire-summary-card">
                                <span>Abastecimentos</span>
                                <strong>{{ $summary['fuel_fillings_count'] }}</strong>
                                <small>Válidos no período</small>
                            </div>
                
                            <div class="tire-summary-card">
                                <span>Litros abastecidos</span>
                                <strong>{{ $number($summary['fuel_liters'], 3) }}</strong>
                                <small>Diesel/ARLA separados abaixo</small>
                            </div>
                
                            <div class="tire-summary-card">
                                <span>Custo abastecimento</span>
                                <strong>{{ $money($summary['fuel_cost']) }}</strong>
                                <small>Custo registrado</small>
                            </div>
                        </div>
                    </div>
                
                    <div class="dossier-summary-group">
                        <h3 class="dossier-summary-title">
                            Indicadores operacionais
                        </h3>                
                        <div class="tire-summary-grid dossier-summary-grid">

                            <div class="tire-summary-card">
                                <span>KM RODADOS</span>
                                <strong>
                                    {{ $summary['operational_indicators']['km_traveled']
                                        ? $number($summary['operational_indicators']['km_traveled'])
                                        : '-' }}
                                </strong>
                                <small>Distância percorrida</small>
                            </div>
                        
                            <div class="tire-summary-card">
                                <span>HORAS TRABALHADAS</span>
                                <strong>
                                    {{ $summary['operational_indicators']['hours_worked']
                                        ? $number($summary['operational_indicators']['hours_worked'],1)
                                        : '-' }}
                                </strong>
                                <small>Horímetro utilizado</small>
                            </div>
                        
                            <div class="tire-summary-card">
                                <span>CONSUMO MÉDIO</span>
                                <strong>
                                    {{ $summary['operational_indicators']['average_km_per_liter']
                                        ? $number($summary['operational_indicators']['average_km_per_liter'],2).' km/L'
                                        : '-' }}
                                </strong>
                                <small>Média calculada</small>
                            </div>
                        
                            <div class="tire-summary-card">
                                <span>CONSUMO POR HORA</span>
                                <strong>
                                    {{ $summary['operational_indicators']['average_liter_per_hour']
                                        ? $number($summary['operational_indicators']['average_liter_per_hour'],2).' L/h'
                                        : '-' }}
                                </strong>
                                <small>Quando houver horímetro</small>
                            </div>
                        
                            <div class="tire-summary-card">
                                <span>CUSTO OPERACIONAL</span>
                                <strong>
                                    {{ $money($summary['operational_indicators']['operational_cost']) }}
                                </strong>
                                <small>Combustível + manutenção</small>
                            </div>
                        
                            <div class="tire-summary-card">
                                <span>CUSTO POR KM</span>
                                <strong>
                                    {{ $summary['operational_indicators']['cost_per_km']
                                        ? 'R$ '.$number($summary['operational_indicators']['cost_per_km'],2)
                                        : '-' }}
                                </strong>
                                <small>Custo médio por quilômetro</small>
                            </div>
                        
                        </div>
                    </div>
                </div>
            </div>
        </details>
        @endif

        @if($showSection('fuel'))
        <details open class="tire-report-section dossier-collapsible">
            <summary>
                <div>
                    <span class="tire-section-kicker">Abastecimentos</span>
                    <h2>Resumo por produto</h2>
                    <p>Diesel e ARLA separados. Cancelados não entram em litros, custos ou consumo.</p>
                </div>
                <span class="dossier-collapse-indicator"></span>

            </summary>
            <div class="dossier-collapsible-body">

                <div class="dossier-product-grid">
                @forelse($fuelByProductList as $product)
                    @php
                        $productConsumption = $fuelConsumptionList->firstWhere('product_name', $product['product_name']);
                
                        $kmConsumption = $productConsumption['km_consumption']['value'] ?? null;
                        $hoursConsumption = $productConsumption['hours_consumption']['value'] ?? null;
                    @endphp
                
                    <div class="dossier-product-card">
                        <span>{{ $product['product_name'] }}</span>
                
                        <strong>{{ $number($product['liters'], 3) }} L</strong>
                
                        <small>
                            {{ $product['fillings_count'] }} abastecimento(s) | {{ $money($product['total_cost']) }}
                        </small>
                
                        <div class="dossier-product-consumption">
                            <div>
                                <span>Consumo KM</span>
                                <strong>
                                    {{ $kmConsumption !== null ? $number($kmConsumption, 2) . ' km/L' : '-' }}
                                </strong>
                            </div>
                
                            <div>
                                <span>Consumo HR</span>
                                <strong>
                                    {{ $hoursConsumption !== null ? $number($hoursConsumption, 2) . ' L/h' : '-' }}
                                </strong>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="dossier-product-card is-empty">
                        <span>Sem abastecimentos</span>
                        <strong>0,000 L</strong>
                        <small>Nenhum registro válido no período.</small>
                    </div>
                @endforelse
            </div>
            </div>
        </details>
        
        <details open class="tire-report-section dossier-collapsible">
            <summary>
                <div>
                    <span class="tire-section-kicker">Abastecimentos</span>
                    <h2>Abastecimentos no periodo</h2>
                    <p>Registros validos do veiculo na unidade ativa, com leituras e custos informados no lancamento.</p>
                </div>
                <span class="dossier-collapse-indicator"></span>
            </summary>
            <div class="dossier-collapsible-body">

            <div class="dossier-table-wrap">
                <table class="dossier-table">
                    <thead>
                        <tr>
                            <th>Data/hora</th>
                            <th>Produto</th>
                            <th>Origem/local</th>
                            <th>Motorista/Condutor</th>
                            <th>KM/HR</th>
                            <th>Litros</th>
                            <th>Custo unit.</th>
                            <th>Custo total</th>
                            <th>Registrado por</th>
                            <th>Observacao</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($fuelFillingsList as $filling)
                            <tr @class(['has-warning' => $filling['vehicle_km'] === null && $filling['vehicle_hours'] === null])>
                                <td>{{ $filling['date'] ? \Carbon\Carbon::parse($filling['date'])->format('d/m/Y H:i') : '-' }}</td>
                                <td><strong>{{ $filling['product_name'] }}</strong></td>
                                <td><strong>{{ $filling['source_label'] ?? 'Tanque da unidade' }}</strong><br><span>{{ $filling['location_label'] ?? $filling['tank_name'] ?? '-' }}</span></td>
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
                                <td>{{ $filling['registered_by_name'] ?? $filling['responsible_name'] ?? 'Não informado' }}</td>
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
        </div>
        </details>
        @endif
        @if($showSection('fuel_consumption'))
        <details open class="tire-report-section dossier-collapsible">
            <summary>
                <div>
                    <span class="tire-section-kicker">Consumo</span>
                    <h2>Consumo e leitura operacional</h2>
                    <p>Calculo conservador: exige pelo menos duas leituras crescentes por produto. Medias nao confiaveis nao sao exibidas.</p>
                </div>
                <span class="dossier-collapse-indicator"></span>
            </summary>
            
            <div class="dossier-collapsible-body">

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

            </div>
        </details>
        @endif
        
        @if($showSection('tires'))

        <details open class="tire-report-section dossier-collapsible">
            <summary>
                <div>
                    <span class="tire-section-kicker">Pneus</span>
                    <h2>Resumo dos pneus</h2>
                    <p>Situação dos pneus instalados, medições de sulcagem e movimentações realizadas no período.</p>
                </div>
                <span class="dossier-collapse-indicator"></span>
            </summary>

            <div class="dossier-collapsible-body">

                <div class="tire-summary-grid four-cards">
                    <article class="tire-summary-card">
                        <span>PNEUS INSTALADOS</span>
            
                        <strong>
                            {{ $tiresCurrent->count() }}
                        </strong>
            
                        <small>Atualmente no veículo</small>
                    </article>
            
                    <article class="tire-summary-card">
                        <span>MEDIÇÕES</span>
            
                        <strong>
                            {{ $tireMeasurements->sum('measurements_count') }}
                        </strong>
            
                        <small>No período</small>
                    </article>
            
                    <article class="tire-summary-card">
                        <span>MOVIMENTAÇÕES</span>        
                        <strong>
                            {{ $tireEvents->count() }}
                        </strong>
            
                        <small>Instalações e retiradas</small>
                    </article>
            
                    <article class="tire-summary-card">
                        <span>MENOR SULCO ATUAL</span>
            
                        <strong>
                            {{ optional($tiresCurrent->sortBy('current_tread_depth')->first())['current_tread_depth']
                                ? $number(optional($tiresCurrent->sortBy('current_tread_depth')->first())['current_tread_depth'],2).' mm'
                                : '-' }}
                        </strong>
            
                        <small>Entre os pneus instalados</small>
                    </article>
                </div>
            
                @php
                    $positionOrder = ['1E', '1D', '2E', '2D', '3E', '3D', '4E', '4D'];
                    $activeTiresByPosition = $tiresCurrent->keyBy('position_code');
                @endphp
                
                <div class="dossier-tire-position-grid">
                    @foreach($positionOrder as $position)
                        @php
                            $activeTire = $activeTiresByPosition->get($position);
                        
                            if (! $activeTire) {
                                continue;
                            }
                        
                            $activeTireMeasurement = $tireMeasurements
                                ->firstWhere('tire_id', $activeTire['tire_id']);
                        
                            $initialTread = $activeTireMeasurement['initial_tread']
                                ?? $activeTire['initial_tread_depth']
                                ?? null;
                        
                            $finalTread = $activeTireMeasurement['final_tread']
                                ?? $activeTire['current_tread_depth']
                                ?? null;
                        
                            $wear = null;
                        
                            if ($initialTread !== null && $finalTread !== null) {
                                $wear = abs((float) $initialTread - (float) $finalTread);
                            }
                        
                            $wearClass = match (true) {
                                $wear === null => 'neutral',
                                $wear > 2 => 'high',
                                $wear > 0.5 => 'medium',
                                default => 'low',
                            };
                        
                            $wearLabel = match ($wearClass) {
                                'high' => 'Alto',
                                'medium' => 'Médio',
                                'low' => 'Baixo',
                                default => 'Sem leitura',
                            };
                        
                            $referenceTread = $activeTire['tread_reference_depth']
                                ?? $activeTire['initial_tread_depth']
                                ?? null;
                            /*
                            |--------------------------------------------------------------------------
                            | Limites operacionais de sulcagem
                            |--------------------------------------------------------------------------
                            | Acima de 4 mm: normal
                            | De 2,01 mm até 4 mm: atenção
                            | Até 2 mm: crítico
                            */
                            $warningTread = 4.0;
                            $criticalTread = 2.0;
                            
                            $treadStatusClass = 'normal';
                            $treadStatusLabel = 'Normal';
                            
                            if ($finalTread !== null) {
                                $currentTread = (float) $finalTread;
                            
                                if ($currentTread <= $criticalTread) {
                                    $treadStatusClass = 'critical';
                                    $treadStatusLabel = 'Crítico';
                                } elseif ($currentTread <= $warningTread) {
                                    $treadStatusClass = 'warning';
                                    $treadStatusLabel = 'Atenção';
                                }
                            }
                            
                            /*
                            |--------------------------------------------------------------------------
                            | Percentual de sulcagem restante
                            |--------------------------------------------------------------------------
                            | Exemplo:
                            | 15 mm inicial e 4 mm atual = 26,7%
                            | 15 mm inicial e 2 mm atual = 13,3%
                            */
                            $treadRemainingPercentage = null;
                            
                            if (
                                $referenceTread !== null
                                && $finalTread !== null
                                && (float) $referenceTread > 0
                            ) {
                                $treadRemainingPercentage = min(
                                    100,
                                    max(
                                        0,
                                        ((float) $finalTread / (float) $referenceTread) * 100
                                    )
                                );
                            }
                            
                            $retreadsCount = (int) ($activeTire['retreads_count'] ?? 0);
                        
                            $kmRun = null;
                        
                            if (
                                ($activeTire['installed_km'] ?? null) !== null
                                && ($vehicle['current_km'] ?? null) !== null
                                && (float) $vehicle['current_km'] >= (float) $activeTire['installed_km']
                            ) {
                                $kmRun =
                                    (float) $vehicle['current_km']
                                    - (float) $activeTire['installed_km'];
                            }
                        @endphp
                
                        <article class="dossier-tire-position-card {{ $activeTire ? 'has-tire' : 'is-empty' }}">
                            <div class="dossier-tire-position-top">
                                <span>Posição {{ $position }}</span>
                
                                @if($activeTire)
                                    <strong>{{ $activeTire['code'] }}</strong>
                                    <small>
                                        {{ trim(($activeTire['brand'] ?? '') . ' ' . ($activeTire['model'] ?? '')) ?: 'Pneu cadastrado' }}
                                    </small>
                                    
                                    <div class="dossier-tire-compact-life {{ $treadStatusClass }}">
                                        <div class="dossier-tire-compact-life-head">
                                            <span>
                                                Sulcagem
                                                <small>{{ $treadStatusLabel }}</small>
                                            </span>
                                        
                                            <strong class="percentSulcagem">
                                                {{ $treadRemainingPercentage !== null
                                                    ? $number($treadRemainingPercentage, 1) . '%'
                                                    : '-' }}
                                            </strong>
                                        </div>
                                    
                                        <div
                                            class="dossier-tire-segments {{ $treadStatusClass }}"
                                            aria-label="Sulcagem restante: {{ $treadRemainingPercentage !== null ? $number($treadRemainingPercentage, 1) . '%' : 'não calculada' }}"
                                        >
                                            @for($segment = 1; $segment <= 20; $segment++)
                                                @php
                                                    $segmentStart = ($segment - 1) * 5;
                                        
                                                    $segmentActive = $treadRemainingPercentage !== null
                                                        && $treadRemainingPercentage > $segmentStart;
                                                @endphp
                                        
                                                <span @class(['is-active' => $segmentActive])></span>
                                            @endfor
                                        </div>
                                    </div>
                                @endif
                            </div>
                
                            @if($activeTire)
                                <div class="dossier-tire-position-metrics">
                                    <div>
                                        <span>Sulco inicial</span>
                                        <strong>{{ $initialTread !== null ? $number($initialTread, 2) . ' mm' : '-' }}</strong>
                                    </div>
                
                                    <div>
                                        <span>Sulco atual</span>
                                        <strong>{{ $finalTread !== null ? $number($finalTread, 2) . ' mm' : '-' }}</strong>
                                    </div>
                
                                    <div>
                                        <span>Medições</span>
                                        <strong>{{ $activeTireMeasurement['measurements_count'] ?? 0 }}</strong>
                                    </div>
                                    
                                    <div>
                                        <span>KM rodado</span>
                                        <strong>{{ $kmRun !== null ? $number($kmRun, 0) . ' km' : '-' }}</strong>
                                    </div>
                                </div>

                                <div class="dossier-muted-line">Instalado por: {{ $activeTire['installed_by_name'] ?? 'Não informado' }}</div>
                                @if(! empty($activeTireMeasurement['measured_by_name']))
                                    <div class="dossier-muted-line">Última medição por: {{ $activeTireMeasurement['measured_by_name'] }}</div>
                                @endif
                                <div class="dossier-tire-footer-badges">
                                    <span class="dossier-tire-wear-badge {{ $wearClass }}">
                                        {{ $wear !== null
                                            ? $number($wear, 2) . ' mm · ' . $wearLabel
                                            : $wearLabel }}
                                    </span>
                                
                                    <span @class([
                                        'dossier-tire-retread-badge',
                                        'has-retread' => $retreadsCount > 0,
                                    ])>
                                        @if($retreadsCount === 0)
                                            Original
                                        @elseif($retreadsCount === 1)
                                            1 recapagem
                                        @else
                                            {{ $retreadsCount }} recapagens
                                        @endif
                                    </span>
                                </div>
                            @endif
                        </article>
                    @endforeach
                </div>        
                @if($tireEvents->isNotEmpty())
            
                <div class="dossier-table-wrap dossier-tire-table-wrap dossier-tire-events-table">
                
                    <table class="dossier-table dossier-tire-table">        
                            <thead>
            
                                <tr>
            
                                    <th>Data</th>
            
                                    <th>Evento</th>
            
                                    <th>Pneu</th>
            
                                    <th>Posição</th>
            
                                    <th>KM</th>
            
                                    <th>Motivo</th>

                                    <th>Autoria</th>
            
                                </tr>
            
                            </thead>
            
                            <tbody>
            
                            @foreach($tireEvents as $event)
            
                                <tr>
            
                                    <td>{{ $formatDate($event['date']) }}</td>    
                                    
                                    <td>{{ $event['label'] }}</td>
            
                                    <td>{{ $event['tire_code'] }}</td>
            
                                    <td>{{ $event['position_code'] }}</td>
            
                                    <td>{{ $number($event['km']) }}</td>
            
                                    <td>{{ $event['reason'] ?: '-' }}</td>

                                    <td>{{ ($event['author_label'] ?? 'Registrado por') . ': ' . ($event['author_name'] ?? 'Não informado') }}</td>
            
                                </tr>
            
                            @endforeach
            
                            </tbody>
            
                        </table>
            
                    </div>
            
                @endif
        
            </div>
        </details>
        @endif


        @if($showSection('maintenances') || $showSection('maintenance_costs'))
        <details open class="tire-report-section dossier-collapsible">
            <summary>
                <div>
                    <span class="tire-section-kicker">Manutencoes</span>
                    <h2>Manutenções no período</h2>
                    <p>Somente registros válidos, sem manutenções canceladas.</p>
                </div>
                <span class="dossier-collapse-indicator"></span>
            </summary>
            
            <div class="dossier-collapsible-body">

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
                            <th>Observação</th>
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
                                    <div class="dossier-muted-line">Aberta por: {{ $maintenance['opened_by_name'] ?? 'Não informado' }}</div>
                                    @if(! empty($maintenance['closed_by_name']))
                                        <div class="dossier-muted-line">Finalizada por: {{ $maintenance['closed_by_name'] }}</div>
                                    @endif

                                    <div class="dossier-muted-line">
                                        {{ $maintenance['items_count'] }} serviço(s)
                                    </div>

                                    @foreach($visibleItems as $item)
                                        <div class="dossier-item-line">
                                            <strong>{{ $item['procedure_name'] }}</strong>
                                            @if($item['maintenance_type'])
                                            <span>{{ $maintenanceTypeLabel($item['maintenance_type']) }}</span>
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
                                        {{ $maintenanceTypeLabel($maintenance['maintenance_type']) }}
                                    @endif
                                </td>
                                <td>{{ $maintenance['provider_name'] ?: '-' }}</td>
                                <td>
                                    KM {{ $maintenance['performed_km'] !== null ? $number($maintenance['performed_km']) : '-' }}
                                    <br>
                                    HR {{ $maintenance['performed_hours'] !== null ? $number($maintenance['performed_hours'], 1) : '-' }}
                                </td>
                                <td>{{ $money($maintenance['total_cost']) }}</td>
                                <td>{{ $observationLabel($maintenance['notes'] ?: $maintenance['reason'] ?: null) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="dossier-empty-cell">Nenhuma manutencao valida encontrada para o periodo.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            </div>
        </details>
        @endif

        @if($showSection('stock'))
        <details open class="tire-report-section dossier-collapsible">
            <summary>
                <div>
                    <span class="tire-section-kicker">Estoque consumido</span>
                    <h2>Peças consumidas do estoque</h2>
                    <p>Consumos vinculados a manutenções válidas do veiculo. Detalhamento separado; nao somado novamente ao custo registrado da ordem.</p>
                </div>
                <span class="dossier-collapse-indicator"></span>
            </summary>
            
            <div class="dossier-collapsible-body">

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

            </div>
        </details>
        @endif


        @if($showSection('km_hr'))
        <details open class="tire-report-section dossier-collapsible">
            <summary>
                <div>
                    <span class="tire-section-kicker">KM / Horimetro</span>
                    <h2>Atualizacoes de KM e horimetro</h2>
                    <p>Secao preparada para exibicao das leituras operacionais do veiculo.</p>
                </div>
                <span class="dossier-collapse-indicator"></span>
            </summary>
            <div class="dossier-collapsible-body">
                <table class="dossier-table compact-table">
                    <thead>
                        <tr>
                            <th>Data/hora</th>
                            <th>Tipo</th>
                            <th>Anterior</th>
                            <th>Novo</th>
                            <th>Origem</th>
                            <th>Atualizado por</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($kmHrLogs as $log)
                            <tr>
                                <td>{{ $log['date'] ? \Carbon\Carbon::parse($log['date'])->format('d/m/Y H:i') : '-' }}</td>
                                <td>{{ $log['type_label'] }}</td>
                                <td>{{ $number($log['old_value']) }}</td>
                                <td>{{ $number($log['new_value']) }}</td>
                                <td>
                                    {{ $log['source_label'] }}
                                    @if(! empty($log['observation']))
                                        <div class="dossier-muted-line">{{ $log['observation'] }}</div>
                                    @endif
                                </td>
                                <td>{{ $log['updated_by_name'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="dossier-empty-cell">Nenhuma atualizacao de KM/horimetro encontrada no periodo.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </details>
        @endif

        @if($showSection('downtime'))
        <details open class="tire-report-section dossier-collapsible">
            <summary>
                <div>
                    <span class="tire-section-kicker">Indisponibilidade</span>
                    <h2>Status operacional e indisponibilidade</h2>
                    <p>Secao preparada para consolidar periodos de indisponibilidade do veiculo.</p>
                </div>
                <span class="dossier-collapse-indicator"></span>
            </summary>
            <div class="dossier-collapsible-body">
                <div class="dossier-empty-cell">Ainda nao ha dados consolidados de downtime para esta secao do dossie.</div>
            </div>
        </details>
        @endif
        @if($showSection('alerts'))
        <details open class="tire-report-section dossier-collapsible">
            <summary>
                    <div>
                        <span class="tire-section-kicker">Alertas</span>
                        <h2>Alertas identificados</h2>
                        <p>Ocorrências relevantes encontradas no período do dossiê.</p>
                    </div>

                <span class="dossier-collapse-indicator"></span>
            </summary>
            
            <div class="dossier-collapsible-body">

                <div class="tire-compact-item">
                    <strong>{{ $summary['alerts_count'] }}</strong>
                    <span>Alerta(s) diagnosticado(s) pelo Dossie nesta etapa.</span>
                </div>

            </div>
        </details>
        @endif

        @if($cancelledList->isNotEmpty())
        <details open class="tire-report-section dossier-collapsible">
            <summary>
                    <div>
                        <span class="tire-section-kicker">Canceladas</span>
                        <h2>Registros cancelados</h2>
                        <p>Manutenções e abastecimentos cancelados exibidos separadamente. Não entram em contagem, litros, custo ou resumo operacional.</p>
                    </div>

                <span class="dossier-collapse-indicator"></span>
            </summary>
            
            <div class="dossier-collapsible-body">

                <div class="dossier-table-wrap">
                    <table class="dossier-table">
                        <thead>
                            <tr>
                                <th>Tipo</th>
                                <th>Data</th>
                                <th>Registro</th>
                                <th>Qtd./Valor</th>
                                <th>Cancelada em</th>
                                <th>Registrado por</th>
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

            </div>
        </details>
        @endif

        @endif
</div>
@endsection
