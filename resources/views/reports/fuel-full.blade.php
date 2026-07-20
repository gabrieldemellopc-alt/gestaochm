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

<div class="reports-page reports-tires-page reports-fuel-page fuel-full-report">
    <div class="reports-hero reports-tires-hero">
        <div>
            <div class="reports-hero-badge">Relatorio completo</div>
            <h1>Relatorio Completo de Abastecimentos</h1>
            <p>
                Unidade {{ $context['location']->name ?? '-' }} /
                divisao {{ $context['division']->name ?? '-' }} no periodo
                {{ $formatDate($filters['start_date']) }} a {{ $formatDate($filters['end_date']) }}.
            </p>
        </div>

        <div class="tire-action-row">
            <a href="{{ route('reports.fuel.index', request()->query()) }}" class="report-module-button secondary">
                Voltar ao painel
            </a>
@if($canExportReportPdf)
<a href="{{ route('reports.fuel.export-pdf', request()->query()) }}" class="report-module-button secondary">
                Exportar PDF
            </a>
@endif
@if($canExportReportExcel)
<a href="{{ route('reports.fuel.export-excel', request()->query()) }}" class="report-module-button secondary">
                Exportar Excel
            </a>
@endif
        </div>
    </div>

    <section class="tire-report-section fuel-report-context">
        <div class="fuel-meta-grid">
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
                <span>Veiculo</span>
                <strong>{{ $selectedVehicle ? $selectedVehicle->name . ' - ' . $selectedVehicle->plate : 'Todos' }}</strong>
            </div>
        </div>

        <div class="fuel-filter-chip-list">
            <span>Produto: {{ $selectedProduct?->name ?? 'Todos' }}</span>
            <span>Tanque: {{ $selectedTank?->name ?? 'Todos' }}</span>
            @if($filters['only_low_tanks'])
                <span>Apenas tanques baixos</span>
            @endif
            @if($filters['only_vehicles_with_consumption'])
                <span>Apenas consumo calculado</span>
            @endif
            @if($filters['include_missing_counters'])
                <span>Diagnostico KM/HR ativo</span>
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
                <h2>Tanques da unidade</h2>
                <p>Esta secao representa o saldo atual e independe do periodo selecionado.</p>
            </div>
        </div>

        <div class="tire-report-table-wrap">
            <table class="tire-report-table fuel-detail-table">
                <thead>
                    <tr>
                        <th>Tanque</th>
                        <th>Produto</th>
                        <th>Capacidade</th>
                        <th>Saldo atual</th>
                        <th>Ocupado</th>
                        <th>Saldo minimo</th>
                        <th>Status</th>
                        <th>Ultimo recebimento</th>
                        <th>Ultimo abastecimento</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tank_summary as $item)
                        <tr class="fuel-tank-row fuel-tank-{{ $item['status'] }}">
                            <td><strong>{{ $item['tank']->name }}</strong></td>
                            <td>{{ $item['product']?->name ?? '-' }}</td>
                            <td>{{ $liters($item['capacity_liters']) }}</td>
                            <td>{{ $liters($item['current_balance_liters']) }}</td>
                            <td>{{ $decimal($item['occupied_percentage']) }}%</td>
                            <td>{{ $liters($item['minimum_balance_liters']) }}</td>
                            <td>
                                <span class="fuel-status-badge status-{{ $item['status'] }}">
                                    {{ $statusLabels[$item['status']] ?? $item['status'] }}
                                </span>
                            </td>
                            <td>{{ $formatDate($item['last_receipt']?->received_at) }}</td>
                            <td>{{ $formatDateTime($item['last_filling']?->filled_at) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="empty-state">Nenhum tanque encontrado para a unidade ativa.</td>
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
                <h2>Recebimentos</h2>
                <p>Entradas validas de combustivel/ARLA no periodo.</p>
            </div>
        </div>

        <div class="tire-report-table-wrap">
            <table class="tire-report-table fuel-detail-table">
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
                        <tr>
                            <td colspan="9" class="empty-state">Nenhum recebimento no periodo.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="tire-report-section">
        <div class="tire-section-header">
            <div>
                <h2>Abastecimentos</h2>
                <p>Saidas operacionais por veiculo no periodo.</p>
            </div>
        </div>

        <div class="tire-report-table-wrap">
            <table class="tire-report-table fuel-detail-table">
                <thead>
                    <tr>
                        <th>Data/hora</th>
                        <th>Veiculo</th>
                        <th>Placa</th>
                        <th>Motorista/Condutor</th>
                        <th>Origem/local</th>
                        <th>Produto</th>
                        <th>KM</th>
                        <th>Horas</th>
                        <th>Litros</th>
                        <th>Custo</th>
                        <th>Registrado por</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($fillings_period as $filling)
                        <tr @class(['fuel-warning-row' => $filling->vehicle_km === null && $filling->vehicle_hours === null])>
                            <td>{{ $formatDateTime($filling->filled_at) }}</td>
                            <td>{{ $filling->vehicle?->name ?? '-' }}</td>
                            <td>{{ $filling->vehicle?->plate ?? '-' }}</td>
                            <td>{{ $filling->driver?->name ?? 'Não informado' }}</td>
                            <td><strong>{{ $filling->source_label }}</strong><br><span>{{ $filling->location_label }}</span></td>
                            <td>{{ $filling->product?->name ?? '-' }}</td>
                            <td>{{ $filling->vehicle_km !== null ? $decimal($filling->vehicle_km, 0) : '-' }}</td>
                            <td>{{ $filling->vehicle_hours !== null ? $decimal($filling->vehicle_hours, 1) : '-' }}</td>
                            <td>{{ $liters($filling->quantity_liters) }}</td>
                            <td>{{ $money($filling->total_cost) }}</td>
                            <td>{{ $filling->responsible?->name ?? 'Não informado' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="empty-state">Nenhum abastecimento no periodo.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if($adjustmentMovements->isNotEmpty())
        <section class="tire-report-section">
            <div class="tire-section-header">
                <div>
                    <h2>Ajustes e reversoes</h2>
                    <p>Movimentos do razao separados do consumo operacional.</p>
                </div>
            </div>

            <div class="tire-report-table-wrap">
                <table class="tire-report-table fuel-detail-table">
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
                            <th>Observacao</th>
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
        </section>
    @endif

    <section class="tire-report-section">
        <div class="tire-section-header">
            <div>
                <span class="tire-section-kicker">Consumo operacional</span>
                <h2>Consumo por veiculo</h2>
                <p>Diesel e ARLA sao avaliados separadamente. Medias so aparecem quando ha base confiavel.</p>
            </div>
        </div>

        <div class="tire-report-table-wrap">
            <table class="tire-report-table fuel-detail-table">
                <thead>
                    <tr>
                        <th>Veiculo</th>
                        <th>Placa</th>
                        <th>Produto</th>
                        <th>Abast.</th>
                        <th>Litros</th>
                        <th>Custo</th>
                        <th>KM inicial/final</th>
                        <th>Horas inicial/final</th>
                        <th>km/L</th>
                        <th>L/h</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($consumption_by_vehicle as $row)
                        <tr @class(['fuel-warning-row' => $row['status'] !== 'calculado'])>
                            <td><strong>{{ $row['vehicle']?->name ?? '-' }}</strong></td>
                            <td>{{ $row['vehicle']?->plate ?? '-' }}</td>
                            <td>{{ $row['product']?->name ?? '-' }}</td>
                            <td>{{ $row['fillings_count'] }}</td>
                            <td>{{ $liters($row['total_liters']) }}</td>
                            <td>{{ $money($row['total_cost']) }}</td>
                            <td>
                                {{ $row['km_consumption']['initial'] !== null ? $decimal($row['km_consumption']['initial'], 0) : '-' }}
                                /
                                {{ $row['km_consumption']['final'] !== null ? $decimal($row['km_consumption']['final'], 0) : '-' }}
                            </td>
                            <td>
                                {{ $row['hours_consumption']['initial'] !== null ? $decimal($row['hours_consumption']['initial'], 1) : '-' }}
                                /
                                {{ $row['hours_consumption']['final'] !== null ? $decimal($row['hours_consumption']['final'], 1) : '-' }}
                            </td>
                            <td>{{ $row['km_consumption']['status'] === 'calculado' ? number_format($row['km_consumption']['value'], 2, ',', '.') : '-' }}</td>
                            <td>{{ $row['hours_consumption']['status'] === 'calculado' ? number_format($row['hours_consumption']['value'], 2, ',', '.') : '-' }}</td>
                            <td>
                                <span class="fuel-status-badge status-{{ $row['status'] === 'calculado' ? 'normal' : 'low' }}">
                                    {{ $consumptionLabel($row['status']) }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="empty-state">Nenhum consumo de veiculo no periodo.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if($selectedVehicle)
        <section class="tire-report-section fuel-vehicle-focus">
            <div class="tire-section-header">
                <div>
                    <span class="tire-section-kicker">Visao individual</span>
                    <h2>{{ $selectedVehicle->name }} - {{ $selectedVehicle->plate }}</h2>
                    <p>Resumo do veiculo filtrado no periodo selecionado.</p>
                </div>
            </div>

            <div class="retread-summary-grid compact">
                <div><span>Abastecimentos</span><strong>{{ $fillings_period->count() }}</strong></div>
                <div><span>Litros</span><strong>{{ $liters($total_filled_liters) }}</strong></div>
                <div><span>Custo</span><strong>{{ $money($total_filled_cost) }}</strong></div>
                <div><span>Sem KM/HR</span><strong>{{ $fillings_without_km_hr->count() }}</strong></div>
            </div>

            <div class="tire-report-columns fuel-panel-columns">
                <div>
                    <h3 class="tire-subtitle">Produtos usados</h3>
                    <div class="tire-compact-list">
                        @forelse($vehicleProductSummary as $row)
                            <div class="tire-compact-item">
                                <strong>{{ $row['product']?->name ?? '-' }}</strong>
                                <span>{{ $row['count'] }} abastecimento(s) | {{ $liters($row['liters']) }} | {{ $money($row['cost']) }}</span>
                            </div>
                        @empty
                            <div class="empty-state">Nenhum produto abastecido no periodo.</div>
                        @endforelse
                    </div>
                </div>

                <div>
                    <h3 class="tire-subtitle">Ultimos abastecimentos</h3>
                    <div class="tire-compact-list">
                        @forelse($fillings_period->take(5) as $filling)
                            <div class="tire-compact-item">
                                <strong>{{ $formatDateTime($filling->filled_at) }} - {{ $filling->product?->name ?? '-' }}</strong>
                                <span>{{ $liters($filling->quantity_liters) }} | KM {{ $filling->vehicle_km ?? '-' }} | HR {{ $filling->vehicle_hours ?? '-' }}</span>
                            </div>
                        @empty
                            <div class="empty-state">Nenhum abastecimento para este veiculo.</div>
                        @endforelse
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
                <p>Itens que pedem conferencia operacional antes de usar medias ou saldos como decisao final.</p>
            </div>
        </div>

        <div class="tire-report-columns fuel-panel-columns">
            <div>
                <h3 class="tire-subtitle">Tanques abaixo do minimo</h3>
                <div class="tire-compact-list">
                    @forelse($low_tanks as $item)
                        <div class="tire-compact-item danger">
                            <strong>{{ $item['tank']->name }} - {{ $item['product']?->name }}</strong>
                            <span>{{ $liters($item['current_balance_liters']) }} | minimo {{ $liters($item['minimum_balance_liters']) }}</span>
                        </div>
                    @empty
                        <div class="empty-state">Nenhum tanque abaixo do minimo.</div>
                    @endforelse
                </div>
            </div>

            <div>
                <h3 class="tire-subtitle">Abastecimentos sem KM/HR</h3>
                <div class="tire-compact-list">
                    @forelse($fillings_without_km_hr as $filling)
                        <div class="tire-compact-item warning">
                            <strong>{{ $filling->vehicle?->plate ?? '-' }} - {{ $formatDateTime($filling->filled_at) }}</strong>
                            <span>{{ $filling->product?->name ?? '-' }} | {{ $liters($filling->quantity_liters) }}</span>
                        </div>
                    @empty
                        <div class="empty-state">Nenhum abastecimento sem KM/HR no periodo.</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="tire-report-table-wrap fuel-alert-table">
            <table class="tire-report-table">
                <thead>
                    <tr>
                        <th>Veiculo</th>
                        <th>Produto</th>
                        <th>Litros</th>
                        <th>Status do calculo</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($invalidConsumptionRows as $row)
                        <tr>
                            <td>{{ $row['vehicle']?->name ?? '-' }} - {{ $row['vehicle']?->plate ?? '-' }}</td>
                            <td>{{ $row['product']?->name ?? '-' }}</td>
                            <td>{{ $liters($row['total_liters']) }}</td>
                            <td>{{ $consumptionLabel($row['status']) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="empty-state">Nenhuma inconsistencia de consumo identificada.</td>
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
                    <h2>Registros cancelados</h2>
                    <p>Cancelados nao compoem saldos, litros, custos, medias, ranking ou consumo.</p>
                </div>
            </div>

            <div class="tire-report-table-wrap">
                <table class="tire-report-table fuel-detail-table">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Tipo</th>
                            <th>Registro</th>
                            <th>Litros</th>
                            <th>Custo</th>
                            <th>Motivo</th>
                            <th>Registrado por</th>
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
        </section>
    @endif
</div>
@endsection
