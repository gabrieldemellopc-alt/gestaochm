@extends('layouts.app')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/pages/reports.css') }}?v=5">
@endpush

@section('content')
@php
    $statusLabels = [
        'available' => 'Disponivel',
        'installed' => 'Instalado',
        'maintenance' => 'Manutencao',
        'discarded' => 'Descartado',
    ];

    $formatDate = fn ($date) => $date ? \Carbon\Carbon::parse($date)->format('d/m/Y') : '-';
    $formatDepth = fn ($value) => $value !== null ? number_format((float) $value, 1, ',', '.') . ' mm' : '-';
@endphp

<div class="reports-page reports-tires-page">
    <div class="reports-hero reports-tires-hero">
        <div>
            <div class="reports-hero-badge">Relatorio completo</div>
            <h1>Relatorio de Pneus</h1>
            <p>
                Visao detalhada da unidade {{ $context['location']->name ?? '-' }}
                no periodo {{ $formatDate($filters['start_date']) }} a {{ $formatDate($filters['end_date']) }}.
            </p>
        </div>

        <div class="tire-action-row">
            <a href="{{ route('reports.tires.index', request()->query()) }}" class="report-module-button secondary">
                Voltar ao painel
            </a>
            <a href="{{ route('reports.tires.export.pdf', request()->query()) }}" class="report-module-button secondary">
                Exportar PDF
            </a>
            <a href="{{ route('reports.tires.export.excel', request()->query()) }}" class="report-module-button">
                Exportar Excel
            </a>
        </div>
    </div>

    @if($filters['period_error'])
        <div class="tire-report-alert">{{ $filters['period_error'] }}</div>
    @endif

    <section class="tire-report-section">
        <div class="tire-section-header">
            <div>
                <span class="tire-section-kicker">Resumo executivo</span>
                <h2>Inventario atual</h2>
                <p>Cancelados nao entram em totais, recapagens, alertas ou sulco atual.</p>
            </div>
        </div>

        <div class="tire-summary-grid">
            <div class="tire-summary-card"><span>Total</span><strong>{{ $summary['total'] }}</strong></div>
            <div class="tire-summary-card"><span>Instalados</span><strong>{{ $summary['installed'] }}</strong></div>
            <div class="tire-summary-card"><span>Disponiveis</span><strong>{{ $summary['available'] }}</strong></div>
            <div class="tire-summary-card warning"><span>Manutencao</span><strong>{{ $summary['maintenance'] }}</strong></div>
            <div class="tire-summary-card danger"><span>Criticos</span><strong>{{ $summary['critical'] }}</strong></div>
            <div class="tire-summary-card danger"><span>Descartados</span><strong>{{ $summary['discarded'] }}</strong></div>
        </div>

        <div class="retread-summary-grid compact">
            <div><span>Sem recapagem</span><strong>{{ $retreadSummary['none'] }}</strong></div>
            <div><span>R1</span><strong>{{ $retreadSummary['r1'] }}</strong></div>
            <div><span>R2</span><strong>{{ $retreadSummary['r2'] }}</strong></div>
            <div><span>R3+</span><strong>{{ $retreadSummary['r3plus'] }}</strong></div>
        </div>
    </section>

    <section class="tire-report-section">
        <div class="tire-section-header">
            <div>
                <h2>Inventario filtrado</h2>
                <p>{{ $tires->count() }} pneu(s) encontrados para os filtros atuais.</p>
            </div>
        </div>

        <div class="tire-report-table-wrap">
            <table class="tire-report-table">
                <thead>
                    <tr>
                        <th>Codigo</th>
                        <th>Status</th>
                        <th>Veiculo atual</th>
                        <th>Marca / Modelo</th>
                        <th>Recapagens</th>
                        <th>Sulco atual</th>
                        <th>Referencia</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tires as $tire)
                        <tr @class(['is-critical' => ($tire->current_tread_depth !== null && (float) $tire->current_tread_depth <= (float) ($tire->critical_tread_depth ?: 3))])>
                            <td><strong>{{ $tire->code }}</strong></td>
                            <td><span class="tire-status-badge status-{{ $tire->status }}">{{ $statusLabels[$tire->status] ?? $tire->status }}</span></td>
                            <td>
                                {{ $tire->activeInstallation?->vehicle?->name ?? '-' }}
                                <small>{{ $tire->activeInstallation?->vehicle?->plate ?? '' }}</small>
                            </td>
                            <td>{{ trim(($tire->brand ?: '-') . ' / ' . ($tire->model ?: '-')) }}</td>
                            <td>{{ (int) $tire->retreads_count > 0 ? 'R' . $tire->retreads_count : 'Nenhuma' }}</td>
                            <td>{{ $formatDepth($tire->current_tread_depth) }}</td>
                            <td>{{ $tire->current_tread_source ?: 'initial' }} - {{ $formatDate($tire->current_tread_date) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="empty-state">Nenhum pneu atende aos filtros atuais.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="tire-report-section">
        <div class="tire-section-header">
            <div>
                <h2>Pneus por veiculo</h2>
                <p>Distribuicao atual de pneus instalados por veiculo da unidade.</p>
            </div>
        </div>

        <div class="vehicle-tire-groups">
            @forelse($tiresByVehicle as $group)
                <div class="vehicle-tire-group">
                    <div>
                        <strong>{{ $group['vehicle']?->name ?? '-' }}</strong>
                        <span>{{ $group['vehicle']?->plate ?? '-' }}</span>
                    </div>
                    <div>
                        {{ $group['installations']->count() }} pneu(s)
                        @if($group['critical_count'] > 0)
                            <span class="tire-status-badge status-critical">{{ $group['critical_count'] }} critico(s)</span>
                        @endif
                    </div>
                </div>
            @empty
                <div class="empty-state">Nenhum pneu instalado em veiculos da unidade ativa.</div>
            @endforelse
        </div>
    </section>

    <section class="tire-report-section">
        <div class="tire-section-header">
            <div>
                <h2>Eventos do periodo</h2>
                <p>Instalacoes, retiradas, medicoes, recapagens, entradas e descartes.</p>
            </div>
        </div>

        <div class="retread-summary-grid compact event-summary-grid">
            <div><span>Instalacoes</span><strong>{{ $eventSummary['installations'] }}</strong></div>
            <div><span>Retiradas</span><strong>{{ $eventSummary['removals'] }}</strong></div>
            <div><span>Medicoes</span><strong>{{ $eventSummary['measurements'] }}</strong></div>
            <div><span>Recapagens</span><strong>{{ $eventSummary['retreads'] }}</strong></div>
            <div><span>Entradas</span><strong>{{ $eventSummary['entries'] }}</strong></div>
            <div><span>Descartes</span><strong>{{ $eventSummary['discards'] }}</strong></div>
        </div>

        <div class="tire-event-list">
            @forelse($events as $event)
                <div class="tire-event-item status-{{ $event['status'] }}">
                    <span>{{ $formatDate($event['date']) }}</span>
                    <strong>{{ $event['type'] }} - {{ $event['title'] }}</strong>
                    <p>{{ $event['description'] }}</p>
                </div>
            @empty
                <div class="empty-state">Nenhum evento no periodo.</div>
            @endforelse
        </div>
    </section>

    <section class="tire-report-section">
        <div class="tire-section-header">
            <div>
                <h2>Alertas operacionais</h2>
                <p>Pneus criticos e pneus sem medicao recente.</p>
            </div>
        </div>

        <div class="tire-report-columns">
            <div>
                <h3 class="tire-subtitle">Pneus criticos</h3>
                <div class="tire-compact-list">
                    @forelse($criticalTires as $tire)
                        <div class="tire-compact-item danger">
                            <strong>{{ $tire->code }}</strong>
                            <span>{{ $formatDepth($tire->current_tread_depth) }} / limite {{ $formatDepth($tire->critical_tread_depth ?: 3) }}</span>
                        </div>
                    @empty
                        <div class="empty-state">Nenhum pneu critico.</div>
                    @endforelse
                </div>
            </div>

            <div>
                <h3 class="tire-subtitle">Sem medicao recente</h3>
                <div class="tire-report-table-wrap compact-table">
                    <table class="tire-report-table">
                        <thead>
                            <tr>
                                <th>Codigo</th>
                                <th>Veiculo</th>
                                <th>Ultima medicao</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($noRecentMeasurements as $tire)
                                <tr>
                                    <td><strong>{{ $tire->code }}</strong></td>
                                    <td>{{ $tire->activeInstallation?->vehicle?->plate ?? '-' }}</td>
                                    <td>{{ $formatDate($tire->latestMeasurement?->measured_at) }}</td>
                                    <td>{{ $statusLabels[$tire->status] ?? $tire->status }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="empty-state">Todos os pneus possuem medicao recente.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>

    @if($context['can_view_cancelled'] && $filters['include_cancelled'])
        <section class="tire-report-section cancelled-section">
            <div class="tire-section-header">
                <div>
                    <h2>Cancelados</h2>
                    <p>Registros cancelados aparecem separados e nao compoem indicadores.</p>
                </div>
            </div>

            <div class="tire-event-list">
                @forelse($cancelledRecords as $record)
                    <div class="tire-event-item status-cancelled">
                        <span>{{ $formatDate($record['date']) }}</span>
                        <strong>{{ $record['type'] }} - {{ $record['title'] }}</strong>
                        <p>{{ $record['reason'] ?: 'Sem motivo registrado' }} @if($record['user']) - {{ $record['user'] }} @endif</p>
                    </div>
                @empty
                    <div class="empty-state">Nenhum cancelamento no periodo.</div>
                @endforelse
            </div>
        </section>
    @endif
</div>
@endsection
