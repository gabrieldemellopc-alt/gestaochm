@extends('layouts.app')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/pages/reports.css') }}?v=4">
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
    $queryWithoutExpand = request()->except('show_all_no_recent');
@endphp

<div class="reports-page reports-tires-page">
    <div class="reports-hero reports-tires-hero">
        <div>
            <div class="reports-hero-badge">Central de Pneus</div>
            <h1>Pneus da unidade</h1>
            <p>
                Inventario atual, eventos do periodo e alertas operacionais de
                {{ $context['location']->name ?? 'unidade ativa' }}.
            </p>
        </div>

        <a href="{{ route('reports.index') }}" class="report-module-button secondary">
            Voltar para relatorios
        </a>
    </div>

    <form method="GET" action="{{ route('reports.tires.index') }}" class="tire-report-filters">
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
                Status do pneu
                <select name="status">
                    <option value="all" @selected($filters['status'] === 'all')>Todos</option>
                    @foreach($statusLabels as $value => $label)
                        <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <label>
                Recapagens
                <select name="retreads">
                    <option value="all" @selected($filters['retreads'] === 'all')>Todas</option>
                    <option value="none" @selected($filters['retreads'] === 'none')>Sem recapagem</option>
                    <option value="r1" @selected($filters['retreads'] === 'r1')>R1</option>
                    <option value="r2" @selected($filters['retreads'] === 'r2')>R2</option>
                    <option value="r3plus" @selected($filters['retreads'] === 'r3plus')>R3 ou mais</option>
                </select>
            </label>
        </div>

        <div class="tire-filter-checks">
            <label>
                <input type="checkbox" name="only_installed" value="1" @checked($filters['only_installed'])>
                Apenas instalados
            </label>

            <label>
                <input type="checkbox" name="only_critical" value="1" @checked($filters['only_critical'])>
                Apenas criticos
            </label>

            @if($context['can_view_cancelled'])
                <label>
                    <input type="checkbox" name="include_cancelled" value="1" @checked($filters['include_cancelled'])>
                    Mostrar cancelados em secao separada
                </label>
            @endif

            <button type="submit" class="report-module-button">Aplicar filtros</button>
        </div>

        <div class="tire-filter-note">
            <strong>Como ler:</strong> status, veiculo, recapagem, instalados e criticos filtram o inventario atual.
            O periodo filtra somente eventos, historico e cancelamentos.
        </div>
    </form>

    @if($filters['period_error'])
        <div class="tire-report-alert">
            {{ $filters['period_error'] }}
        </div>
    @endif

    <section class="tire-report-section">
        <div class="tire-section-header">
            <div>
                <span class="tire-section-kicker">A. Estado atual</span>
                <h2>Inventario atual da unidade</h2>
                <p>Dados de estoque e instalacao atuais. Estes numeros nao dependem do periodo selecionado.</p>
            </div>
        </div>

        <div class="tire-summary-grid">
            <div class="tire-summary-card"><span>Total</span><strong>{{ $summary['total'] }}</strong></div>
            <div class="tire-summary-card"><span>Instalados</span><strong>{{ $summary['installed'] }}</strong></div>
            <div class="tire-summary-card"><span>Disponiveis</span><strong>{{ $summary['available'] }}</strong></div>
            <div class="tire-summary-card warning"><span>Manutencao</span><strong>{{ $summary['maintenance'] }}</strong></div>
            <div class="tire-summary-card danger"><span>Descartados</span><strong>{{ $summary['discarded'] }}</strong></div>
            <div class="tire-summary-card danger"><span>Sulco critico</span><strong>{{ $summary['critical'] }}</strong></div>
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
                <h2>Inventario atual filtrado</h2>
                <p>
                    {{ $tires->count() }} pneu(s) encontrados. O periodo nao altera esta tabela.
                </p>
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
                            <td>
                                <strong>{{ $tire->code }}</strong>
                                @if((int) $tire->retreads_count > 0)
                                    <span class="tire-r-badge">R{{ $tire->retreads_count }}</span>
                                @endif
                            </td>
                            <td>
                                <span class="tire-status-badge status-{{ $tire->status }}">
                                    {{ $statusLabels[$tire->status] ?? $tire->status }}
                                </span>
                            </td>
                            <td>
                                {{ $tire->activeInstallation?->vehicle?->name ?? '-' }}
                                @if($tire->activeInstallation?->vehicle?->plate)
                                    <small>{{ $tire->activeInstallation->vehicle->plate }}</small>
                                @endif
                            </td>
                            <td>{{ trim(($tire->brand ?: '-') . ' / ' . ($tire->model ?: '-')) }}</td>
                            <td>{{ (int) $tire->retreads_count > 0 ? 'R' . $tire->retreads_count : 'Nenhuma' }}</td>
                            <td>{{ $formatDepth($tire->current_tread_depth) }}</td>
                            <td>
                                @if($tire->current_tread_source === 'retread')
                                    Recapagem em {{ $formatDate($tire->current_tread_date) }}
                                @elseif($tire->current_tread_source === 'measurement')
                                    Ultima medicao em {{ $formatDate($tire->current_tread_date) }}
                                @else
                                    Sulco inicial
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="empty-state">
                                Nenhum pneu do inventario atual atende aos filtros de veiculo, status, recapagem,
                                instalados ou criticos. O periodo selecionado nao interfere neste resultado.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="tire-report-section">
        <div class="tire-section-header">
            <div>
                <span class="tire-section-kicker">B. Movimento no periodo</span>
                <h2>Eventos do periodo selecionado</h2>
                <p>Instalacoes, retiradas, medicoes, recapagens, descartes e cancelamentos separados.</p>
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

        @if($filters['period_error'])
            <div class="empty-state">Eventos nao foram calculados porque o periodo esta invertido.</div>
        @else
            <div class="tire-event-list">
                @forelse($events as $event)
                    <div class="tire-event-item status-{{ $event['status'] }}">
                        <span>{{ $formatDate($event['date']) }}</span>
                        <strong>{{ $event['type'] }} - {{ $event['title'] }}</strong>
                        <p>{{ $event['description'] }}</p>
                    </div>
                @empty
                    <div class="empty-state">Nenhum evento de pneus encontrado no periodo selecionado.</div>
                @endforelse
            </div>
        @endif
    </section>

    @if($context['can_view_cancelled'] && $filters['include_cancelled'])
        <section class="tire-report-section cancelled-section">
            <div class="tire-section-header">
                <div>
                    <h2>Cancelamentos no periodo</h2>
                    <p>Exibidos apenas para manager/admin. Nao entram em totais, alertas ou sulco atual.</p>
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
                    <div class="empty-state">Nenhum registro cancelado no periodo selecionado.</div>
                @endforelse
            </div>
        </section>
    @endif

    <section class="tire-report-section">
        <div class="tire-section-header">
            <div>
                <span class="tire-section-kicker">C. Alertas atuais</span>
                <h2>Alertas operacionais atuais</h2>
                <p>Pontos de atencao do inventario atual. Estes alertas nao dependem do periodo.</p>
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
                        <div class="empty-state">Nenhum pneu critico na unidade ativa.</div>
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
                                <th>Dias</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($noRecentMeasurements as $tire)
                                @php
                                    $lastMeasurement = $tire->latestMeasurement?->measured_at;
                                    $daysWithoutMeasurement = $lastMeasurement
                                        ? \Carbon\Carbon::parse($lastMeasurement)->diffInDays(now())
                                        : null;
                                @endphp
                                <tr>
                                    <td><strong>{{ $tire->code }}</strong></td>
                                    <td>{{ $tire->activeInstallation?->vehicle?->plate ?? '-' }}</td>
                                    <td>{{ $formatDate($lastMeasurement) }}</td>
                                    <td>{{ $daysWithoutMeasurement !== null ? $daysWithoutMeasurement : 'Sem registro' }}</td>
                                    <td>{{ $statusLabels[$tire->status] ?? $tire->status }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="empty-state">Todos os pneus possuem medicao recente.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($noRecentMeasurementsTotal > 10 && ! $filters['show_all_no_recent'])
                    <a
                        class="tire-inline-action"
                        href="{{ route('reports.tires.index', array_merge(request()->query(), ['show_all_no_recent' => 1])) }}"
                    >
                        Ver todos os {{ $noRecentMeasurementsTotal }} pneus sem medicao recente
                    </a>
                @elseif($filters['show_all_no_recent'])
                    <a
                        class="tire-inline-action"
                        href="{{ route('reports.tires.index', $queryWithoutExpand) }}"
                    >
                        Mostrar apenas os 10 mais relevantes
                    </a>
                @endif
            </div>
        </div>
    </section>

    @if($filters['vehicle_id'])
        <section class="tire-report-section">
            <div class="tire-section-header">
                <div>
                    <h2>Veiculo selecionado</h2>
                    <p>Pneus atualmente instalados no veiculo filtrado.</p>
                </div>
            </div>

            <div class="tire-card-grid">
                @forelse($vehicleInstalledTires as $installation)
                    <div class="vehicle-tire-card">
                        <span>Posicao {{ $installation->position_code }}</span>
                        <strong>{{ $installation->tire?->code ?? '-' }}</strong>
                        <small>{{ $installation->tire?->brand ?? '-' }} / {{ $installation->tire?->model ?? '-' }}</small>
                        <p>Sulco atual: {{ $formatDepth($installation->tire?->current_tread_depth) }}</p>
                        <p>Instalado em: {{ $formatDate($installation->installed_at) }}</p>
                    </div>
                @empty
                    <div class="empty-state">Nenhum pneu instalado no veiculo selecionado.</div>
                @endforelse
            </div>
        </section>
    @endif

    <section class="tire-report-section">
        <div class="tire-section-header">
            <div>
                <h2>Pneus por veiculo</h2>
                <p>Distribuicao atual dos pneus instalados na unidade.</p>
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
</div>
@endsection
