<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">

    <style>
        @page {
            margin: 26px 28px 34px;
        }

        body {
            margin: 0;
            font-family: DejaVu Sans, sans-serif;
            color: #111827;
            font-size: 10px;
            line-height: 1.4;
        }

        * {
            box-sizing: border-box;
        }

        .report-header {
            width: 100%;
            display: table;
            margin-bottom: 22px;
            padding-bottom: 16px;
            border-bottom: 2px solid #dbe2ea;
        }

        .report-header-left,
        .report-header-center,
        .report-header-right {
            display: table-cell;
            vertical-align: middle;
        }

        .report-header-left {
            width: 115px;
        }

        .report-header-center {
            text-align: center;
        }

        .report-header-right {
            width: 165px;
            color: #64748b;
            font-size: 9px;
            text-align: right;
        }

        .report-logo {
            width: 88px;
            max-height: 60px;
        }

        .report-title {
            color: #0f172a;
            font-size: 20px;
            font-weight: bold;
            line-height: 1.2;
        }

        .report-subtitle {
            margin-top: 5px;
            color: #64748b;
            font-size: 10px;
        }

        .report-meta-item {
            margin-bottom: 8px;
        }

        .vehicle-header {
            margin-bottom: 18px;
            padding: 14px;
            border: 1px solid #dbe2ea;
            border-radius: 8px;
            background: #f8fafc;
        }

        .vehicle-name {
            margin: 0 0 3px;
            color: #0f172a;
            font-size: 18px;
            font-weight: bold;
        }

        .vehicle-description {
            color: #64748b;
            font-size: 10px;
        }

        .vehicle-grid,
        .kpi-grid {
            width: 100%;
            display: table;
            table-layout: fixed;
            border-spacing: 7px;
            margin: 7px -7px 0;
        }

        .vehicle-cell,
        .kpi-card {
            display: table-cell;
            padding: 10px;
            border: 1px solid #dbe2ea;
            border-radius: 7px;
            vertical-align: top;
        }

        .vehicle-cell span,
        .kpi-card span {
            display: block;
            margin-bottom: 4px;
            color: #64748b;
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .vehicle-cell strong {
            color: #0f172a;
            font-size: 10px;
        }

        .kpi-card strong {
            display: block;
            color: #0f172a;
            font-size: 16px;
            line-height: 1.2;
        }

        .kpi-card small {
            display: block;
            margin-top: 4px;
            color: #64748b;
            font-size: 8px;
        }

        .section {
            margin-top: 22px;
            page-break-inside: avoid;
        }

        .section.can-break {
            page-break-inside: auto;
        }

        .section-kicker {
            color: #64748b;
            font-size: 8px;
            font-weight: bold;
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        .section-title {
            margin: 2px 0 4px;
            color: #0f172a;
            font-size: 14px;
            font-weight: bold;
        }

        .section-description {
            margin: 0 0 10px;
            color: #64748b;
            font-size: 9px;
        }

        .subsection-title {
            margin: 12px 0 4px;
            color: #334155;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
        }

        table {
            width: 100%;
            margin-top: 8px;
            border-collapse: collapse;
            page-break-inside: auto;
        }

        thead {
            display: table-header-group;
        }

        tr {
            page-break-inside: avoid;
        }

        th {
            padding: 7px 6px;
            color: #fff;
            background: #1e293b;
            font-size: 7px;
            text-align: left;
            text-transform: uppercase;
        }

        td {
            padding: 7px 6px;
            border-bottom: 1px solid #e2e8f0;
            color: #1f2937;
            font-size: 8px;
            vertical-align: top;
        }

        td strong {
            color: #0f172a;
        }

        .muted {
            display: block;
            margin-top: 2px;
            color: #64748b;
            font-size: 7px;
        }

        .tire-grid {
            width: 100%;
            display: table;
            border-spacing: 8px;
            margin: 8px -8px 0;
        }

        .tire-row {
            display: table-row;
        }

        .tire-card {
            display: table-cell;
            width: 50%;
            padding: 10px;
            border: 1px solid #dbe2ea;
            border-radius: 7px;
            vertical-align: top;
        }

        .tire-position {
            color: #64748b;
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .tire-code {
            margin: 3px 0;
            color: #0f172a;
            font-size: 12px;
            font-weight: bold;
        }

        .tire-metrics {
            width: 100%;
            margin-top: 7px;
            border-collapse: separate;
            border-spacing: 4px;
        }

        .tire-metrics td {
            padding: 6px;
            border: 1px solid #e2e8f0;
            font-size: 7px;
        }

        .badge {
            display: inline-block;
            padding: 3px 7px;
            border-radius: 999px;
            font-size: 7px;
            font-weight: bold;
        }

        .badge-success {
            color: #166534;
            background: #dcfce7;
        }

        .badge-warning {
            color: #92400e;
            background: #fef3c7;
        }

        .badge-danger {
            color: #991b1b;
            background: #fee2e2;
        }

        .empty {
            padding: 12px;
            border: 1px dashed #cbd5e1;
            color: #64748b;
            text-align: center;
        }

        .footer {
            margin-top: 28px;
            padding-top: 10px;
            border-top: 1px solid #dbe2ea;
            color: #94a3b8;
            font-size: 8px;
            text-align: center;
        }
    </style>
</head>

<body>
@php
    $filters = $applied_filters;

    $formatDate = fn ($date) => $date
        ? \Carbon\Carbon::parse($date)->format('d/m/Y')
        : '-';

    $number = fn ($value, $decimals = 0) => $value !== null
        ? number_format((float) $value, $decimals, ',', '.')
        : '-';

    $money = fn ($value) => $value !== null
        ? 'R$ ' . number_format((float) $value, 2, ',', '.')
        : '-';

    $summary = $executive_summary;

    $maintenancesList = collect($maintenances);
    $stockConsumptionList = collect($stock_consumption);
    $fuelFillingsList = collect($fuel_fillings);
    $fuelConsumptionList = collect($fuel_consumption ?? []);
    $fuelByProductList = collect($summary['fuel_by_product'] ?? []);
    $tiresCurrent = collect($tires_current ?? []);
    $tireEvents = collect($tire_events ?? []);
    $tireMeasurements = collect($tire_measurements ?? []);
    $cancelledList = collect($cancelled_records ?? []);

    $selectedSections = collect($filters['sections'] ?? []);
    $sectionConfigEnabled = (bool) ($filters['section_config'] ?? false);

    $showSection = fn (string $section) =>
        ! $sectionConfigEnabled || $selectedSections->contains($section);
    $label = fn (string $domain, $value, ?string $fallback = null) => \App\Support\ChmLabel::for($domain, $value, $fallback);
    $statusLabel = fn ($value) => $label('operational_status', $value);
    $maintenanceTypeLabel = fn ($value) => $label('maintenance_type', $value);
    $consumptionStatusLabel = fn ($status) => $label('fuel_consumption_status', $status);
    $observationLabel = fn ($value) => \App\Support\ChmLabel::knownToken([
        'maintenance_type',
        'service_type',
        'execution_type',
        'workflow_status',
    ], $value);

    $periodStart = $filters['start_date'] ?? null;
    $periodEnd = $filters['end_date'] ?? null;
@endphp

<div class="report-header">
    <div class="report-header-left">
        @if(($context['division']->logo ?? null))
            <img
                src="{{ public_path('images/' . $context['division']->logo) }}"
                class="report-logo"
            >
        @else
            <img
                src="{{ public_path('images/logo-chm.png') }}"
                class="report-logo"
            >
        @endif
    </div>

    <div class="report-header-center">
        <div class="report-title">
            DOSSIÊ INDIVIDUAL DO VEÍCULO
        </div>

        <div class="report-subtitle">
            Prontuário técnico e operacional
        </div>
    </div>

    <div class="report-header-right">
        <div class="report-meta-item">
            <strong>Gerado em:</strong><br>
            {{ now()->format('d/m/Y H:i') }}
        </div>

        <div class="report-meta-item">
            <strong>Período:</strong><br>
            {{ $formatDate($periodStart) }}
            →
            {{ $formatDate($periodEnd) }}
        </div>
    </div>
</div>

<div class="vehicle-header">
    <div class="vehicle-name">
        {{ $vehicle['name'] }} — {{ $vehicle['plate'] }}
    </div>

    <div class="vehicle-description">
        {{ $vehicle['brand'] ?: '-' }}
        •
        {{ $vehicle['vehicle_model'] ?: '-' }}
        •
        {{ $vehicle['year'] ?: '-' }}
    </div>

    <div class="vehicle-grid">
        <div class="vehicle-cell">
            <span>Status</span>
            <strong>{{ $statusLabel($vehicle['status']) }}</strong>
        </div>

        <div class="vehicle-cell">
            <span>Status operacional</span>
            <strong>{{ $statusLabel($vehicle['operational_status']) }}</strong>
        </div>

        <div class="vehicle-cell">
            <span>KM atual</span>
            <strong>{{ $number($vehicle['current_km']) }}</strong>
        </div>

        <div class="vehicle-cell">
            <span>Horímetro atual</span>
            <strong>{{ $number($vehicle['current_hours'], 1) }}</strong>
        </div>
    </div>

    <div class="vehicle-grid">
        <div class="vehicle-cell">
            <span>Patrimônio</span>
            <strong>{{ $vehicle['asset_code'] ?: 'Não informado' }}</strong>
        </div>

        <div class="vehicle-cell">
            <span>Unidade</span>
            <strong>{{ $vehicle['location']?->name ?? '-' }}</strong>
        </div>

        <div class="vehicle-cell">
            <span>Divisão</span>
            <strong>{{ $vehicle['division']?->name ?? '-' }}</strong>
        </div>

        <div class="vehicle-cell">
            <span>Tipo</span>
            <strong>{{ $vehicle['type'] ?: '-' }}</strong>
        </div>
    </div>
</div>

@if($showSection('summary') || $showSection('maintenance_costs'))
    <div class="section">
        <div class="section-kicker">Resumo executivo</div>
        <div class="section-title">Indicadores do período</div>

        <div class="kpi-grid">
            <div class="kpi-card">
                <span>Manutenções</span>
                <strong>{{ $summary['maintenance_count'] }}</strong>
                <small>Registros válidos</small>
            </div>

            <div class="kpi-card">
                <span>Custo de manutenção</span>
                <strong>{{ $money($summary['maintenance_cost_registered']) }}</strong>
                <small>Total oficial das ordens</small>
            </div>

            <div class="kpi-card">
                <span>Abastecimentos</span>
                <strong>{{ $summary['fuel_fillings_count'] }}</strong>
                <small>{{ $number($summary['fuel_liters'], 3) }} litros</small>
            </div>
        </div>

        <div class="kpi-grid">
            <div class="kpi-card">
                <span>KM rodados</span>
                <strong>{{ $number($summary['operational_indicators']['km_traveled'] ?? null) }}</strong>
                <small>Distância calculada</small>
            </div>

            <div class="kpi-card">
                <span>Consumo médio</span>
                <strong>
                    {{
                        isset($summary['operational_indicators']['average_km_per_liter'])
                        && $summary['operational_indicators']['average_km_per_liter'] !== null
                            ? $number($summary['operational_indicators']['average_km_per_liter'], 2) . ' km/L'
                            : '-'
                    }}
                </strong>
                <small>Baseado nos abastecimentos</small>
            </div>

            <div class="kpi-card">
                <span>Custo operacional</span>
                <strong>{{ $money($summary['operational_indicators']['operational_cost'] ?? null) }}</strong>
                <small>Manutenção + combustível</small>
            </div>
        </div>
    </div>
@endif

@if($showSection('maintenances'))
    <div class="section can-break">
        <div class="section-kicker">Manutenções</div>
        <div class="section-title">Manutenções no período</div>

        <table>
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Ordem/procedimento</th>
                    <th>Tipo</th>
                    <th>KM/HR</th>
                    <th>Valor</th>
                    <th>Observação</th>
                </tr>
            </thead>

            <tbody>
                @forelse($maintenancesList as $maintenance)
                    <tr>
                        <td>{{ $formatDate($maintenance['date']) }}</td>

                        <td>
                            <strong>Ordem #{{ $maintenance['id'] }}</strong>
                            <span class="muted">{{ $maintenance['procedure_summary'] }}</span>
                        </td>

                        <td>
                            {{ $maintenanceTypeLabel($maintenance['maintenance_type']) }}
                        </td>

                        <td>
                            KM {{ $number($maintenance['performed_km']) }}
                            <br>
                            HR {{ $number($maintenance['performed_hours'], 1) }}
                        </td>

                        <td>{{ $money($maintenance['total_cost']) }}</td>

                        <td>
                            {{ $observationLabel($maintenance['notes'] ?: $maintenance['reason'] ?: null) }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="empty">
                            Nenhuma manutenção encontrada no período.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endif

@if($showSection('stock'))
    <div class="section can-break">
        <div class="section-kicker">Estoque</div>
        <div class="section-title">Peças consumidas</div>

        <table>
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Ordem</th>
                    <th>Item</th>
                    <th>Categoria</th>
                    <th>Quantidade</th>
                    <th>Total</th>
                </tr>
            </thead>

            <tbody>
                @forelse($stockConsumptionList as $movement)
                    <tr>
                        <td>{{ $formatDate($movement['date']) }}</td>
                        <td>#{{ $movement['maintenance_id'] }}</td>
                        <td>{{ $movement['item_name'] }}</td>
                        <td>{{ $movement['category_name'] }}</td>
                        <td>{{ $number($movement['quantity'], 2) }}</td>
                        <td>{{ $money($movement['total_cost']) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="empty">
                            Nenhuma peça consumida no período.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endif

@if($showSection('fuel'))
    <div class="section can-break">
        <div class="section-kicker">Abastecimentos</div>
        <div class="section-title">Abastecimentos no período</div>

        <table>
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Produto</th>
                    <th>Tanque</th>
                    <th>Motorista/Condutor</th>
                    <th>Registrado por</th>
                    <th>KM/HR</th>
                    <th>Litros</th>
                    <th>Custo</th>
                </tr>
            </thead>

            <tbody>
                @forelse($fuelFillingsList as $filling)
                    <tr>
                        <td>
                            {{
                                $filling['date']
                                    ? \Carbon\Carbon::parse($filling['date'])->format('d/m/Y H:i')
                                    : '-'
                            }}
                        </td>

                        <td>{{ $filling['product_name'] }}</td>
                        <td>{{ $filling['tank_name'] }}</td>
                        <td>{{ $filling['driver_name'] ?? 'Não informado' }}</td>
                        <td>{{ $filling['registered_by_name'] ?? $filling['responsible_name'] ?? 'Não informado' }}</td>

                        <td>
                            KM {{ $number($filling['vehicle_km']) }}
                            <br>
                            HR {{ $number($filling['vehicle_hours'], 1) }}
                        </td>

                        <td>{{ $number($filling['quantity_liters'], 3) }} L</td>
                        <td>{{ $money($filling['total_cost']) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="empty">
                            Nenhum abastecimento encontrado no período.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endif

@if($showSection('fuel_consumption'))
    <div class="section can-break">
        <div class="section-kicker">Consumo</div>
        <div class="section-title">Consumo e leitura operacional</div>

        <table>
            <thead>
                <tr>
                    <th>Produto</th>
                    <th>Registros</th>
                    <th>Litros</th>
                    <th>Custo</th>
                    <th>km/L</th>
                    <th>L/h</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($fuelConsumptionList as $consumption)
                    <tr>
                        <td>{{ $consumption['product_name'] }}</td>
                        <td>{{ $consumption['fillings_count'] }}</td>
                        <td>{{ $number($consumption['total_liters'], 3) }} L</td>
                        <td>{{ $money($consumption['total_cost']) }}</td>
                        <td>{{ $consumption['km_consumption']['value'] !== null ? $number($consumption['km_consumption']['value'], 2) . ' km/L' : '-' }}</td>
                        <td>{{ $consumption['hours_consumption']['value'] !== null ? $number($consumption['hours_consumption']['value'], 2) . ' L/h' : '-' }}</td>
                        <td>{{ $consumptionStatusLabel($consumption['status']) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="empty">Sem base de abastecimento para diagnostico de consumo no periodo.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endif
@if($showSection('tires'))
    <div class="section can-break">
        <div class="section-kicker">Pneus</div>
        <div class="section-title">Pneus atualmente instalados</div>

        @php
            $positionOrder = ['1E', '1D', '2E', '2D', '3E', '3D', '4E', '4D'];
            $activeTiresByPosition = $tiresCurrent->keyBy('position_code');
            $visiblePositions = collect($positionOrder)
                ->filter(fn ($position) => $activeTiresByPosition->has($position))
                ->values();
        @endphp

        @if($visiblePositions->isEmpty())
            <div class="empty">Nenhum pneu ativo no veículo.</div>
        @else
            <div class="tire-grid">
                @foreach($visiblePositions->chunk(2) as $positionRow)
                    <div class="tire-row">
                        @foreach($positionRow as $position)
                            @php
                                $activeTire = $activeTiresByPosition->get($position);

                                $measurement = $tireMeasurements
                                    ->firstWhere('tire_id', $activeTire['tire_id']);

                                $initialTread =
                                    $measurement['initial_tread']
                                    ?? $activeTire['initial_tread_depth']
                                    ?? null;

                                $currentTread =
                                    $measurement['final_tread']
                                    ?? $activeTire['current_tread_depth']
                                    ?? null;

                                $wear = (
                                    $initialTread !== null
                                    && $currentTread !== null
                                )
                                    ? abs((float) $initialTread - (float) $currentTread)
                                    : null;

                                $kmRun = (
                                    ($activeTire['installed_km'] ?? null) !== null
                                    && ($vehicle['current_km'] ?? null) !== null
                                    && (float) $vehicle['current_km'] >= (float) $activeTire['installed_km']
                                )
                                    ? (float) $vehicle['current_km'] - (float) $activeTire['installed_km']
                                    : null;

                                $wearClass = match (true) {
                                    $wear === null => '',
                                    $wear > 2 => 'badge-danger',
                                    $wear > .5 => 'badge-warning',
                                    default => 'badge-success',
                                };
                            @endphp

                            <div class="tire-card">
                                <div class="tire-position">
                                    Posição {{ $position }}
                                </div>

                                <div class="tire-code">
                                    {{ $activeTire['code'] }}
                                </div>

                                <div class="muted">
                                    {{ trim(
                                        ($activeTire['brand'] ?? '')
                                        . ' '
                                        . ($activeTire['model'] ?? '')
                                    ) }}
                                </div>

                                <table class="tire-metrics">
                                    <tr>
                                        <td>
                                            Sulco inicial<br>
                                            <strong>{{ $number($initialTread, 2) }} mm</strong>
                                        </td>

                                        <td>
                                            Sulco atual<br>
                                            <strong>{{ $number($currentTread, 2) }} mm</strong>
                                        </td>

                                        <td>
                                            KM rodado<br>
                                            <strong>{{ $number($kmRun) }} km</strong>
                                        </td>
                                    </tr>
                                </table>

                                @if($wear !== null)
                                    <span class="badge {{ $wearClass }}">
                                        Desgaste: {{ $number($wear, 2) }} mm
                                    </span>
                                @endif
                            </div>
                        @endforeach

                        @if($positionRow->count() === 1)
                            <div class="tire-card" style="border:none;"></div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        @if($tireEvents->isNotEmpty())
            <div class="subsection-title">
                Movimentações no período
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Evento</th>
                        <th>Pneu</th>
                        <th>Posição</th>
                        <th>KM</th>
                        <th>Motivo</th>
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
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endif

@if($showSection('km_hr'))
    <div class="section can-break">
        <div class="section-kicker">KM / Horimetro</div>
        <div class="section-title">Atualizacoes de KM e horimetro</div>
        <div class="empty">Ainda nao ha logs consolidados de KM/horimetro para esta secao do dossie.</div>
    </div>
@endif

@if($showSection('downtime'))
    <div class="section can-break">
        <div class="section-kicker">Indisponibilidade</div>
        <div class="section-title">Status operacional e indisponibilidade</div>
        <div class="empty">Ainda nao ha dados consolidados de downtime para esta secao do dossie.</div>
    </div>
@endif

@if($showSection('alerts'))
    <div class="section can-break">
        <div class="section-kicker">Alertas</div>
        <div class="section-title">Alertas e preventivas</div>
        <div class="empty">{{ ($summary['alerts_count'] ?? 0) > 0 ? ($summary['alerts_count'] . ' alerta(s) diagnosticado(s) nesta etapa.') : 'Nenhum alerta consolidado para esta secao do dossie.' }}</div>
    </div>
@endif
@if($cancelledList->isNotEmpty())
    <div class="section can-break">
        <div class="section-kicker">Cancelados</div>
        <div class="section-title">Registros cancelados</div>

        <p class="section-description">
            Estes registros são exibidos apenas para conferência e não compõem os indicadores.
        </p>

        <table>
            <thead>
                <tr>
                    <th>Tipo</th>
                    <th>Data</th>
                    <th>Registro</th>
                    <th>Valor</th>
                    <th>Cancelado por</th>
                    <th>Motivo</th>
                </tr>
            </thead>

            <tbody>
                @foreach($cancelledList as $cancelled)
                    <tr>
                        <td>{{ $cancelled['record_type'] ?? '-' }}</td>
                        <td>{{ $formatDate($cancelled['date']) }}</td>
                        <td>{{ $cancelled['record_label'] ?? '-' }}</td>
                        <td>{{ $money($cancelled['total_cost'] ?? 0) }}</td>
                        <td>{{ $cancelled['cancelled_by'] ?: '-' }}</td>
                        <td>{{ $cancelled['cancel_reason'] ?: '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

<div class="footer">
    Dossiê gerado automaticamente pelo CHM —
    {{ now()->format('d/m/Y H:i') }}
</div>
</body>
</html>