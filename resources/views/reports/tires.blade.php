@extends('layouts.app')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/pages/reports.css') }}?v=3">
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
            <div class="reports-hero-badge">Relatorio de Pneus</div>
            <h1>Pneus da unidade</h1>
            <p>
                Inventario, eventos, recapagens e alertas de pneus em
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
                Status
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
                    Incluir cancelados em secao separada
                </label>
            @endif

            <button type="submit" class="report-module-button">Aplicar filtros</button>
        </div>
    </form>

    <div class="tire-summary-grid">
        <div class="tire-summary-card">
            <span>Total</span>
            <strong>{{ $summary['total'] }}</strong>
        </div>
        <div class="tire-summary-card">
            <span>Instalados</span>
            <strong>{{ $summary['installed'] }}</strong>
        </div>
        <div class="tire-summary-card">
            <span>Disponiveis</span>
            <strong>{{ $summary['available'] }}</strong>
        </div>
        <div class="tire-summary-card warning">
            <span>Manutencao</span>
            <strong>{{ $summary['maintenance'] }}</strong>
        </div>
        <div class="tire-summary-card danger">
            <span>Descartados</span>
            <strong>{{ $summary['discarded'] }}</strong>
        </div>
        <div class="tire-summary-card danger">
            <span>Sulco critico</span>
            <strong>{{ $summary['critical'] }}</strong>
        </div>
    </div>

    <div class="tire-report-section">
        <div class="tire-section-header">
            <div>
                <h2>Recapagens</h2>
                <p>Contagem baseada apenas em recapagens ativas, sem registros cancelados.</p>
            </div>
        </div>

        <div class="retread-summary-grid">
            <div><span>Sem recapagem</span><strong>{{ $retreadSummary['none'] }}</strong></div>
            <div><span>R1</span><strong>{{ $retreadSummary['r1'] }}</strong></div>
            <div><span>R2</span><strong>{{ $retreadSummary['r2'] }}</strong></div>
            <div><span>R3+</span><strong>{{ $retreadSummary['r3plus'] }}</strong></div>
        </div>
    </div>

    <div class="tire-report-section">
        <div class="tire-section-header">
            <div>
                <h2>Pneus filtrados</h2>
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
                            <td colspan="7" class="empty-state">Nenhum pneu encontrado para os filtros atuais.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="tire-report-columns">
        <section class="tire-report-section">
            <div class="tire-section-header">
                <div>
                    <h2>Pneus criticos</h2>
                    <p>Sulco atual igual ou abaixo do limite critico configurado.</p>
                </div>
            </div>

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
        </section>

        <section class="tire-report-section">
            <div class="tire-section-header">
                <div>
                    <h2>Sem medicao recente</h2>
                    <p>Mais de 60 dias sem medicao ou sem medicao registrada.</p>
                </div>
            </div>

            <div class="tire-compact-list">
                @forelse($noRecentMeasurements as $tire)
                    <div class="tire-compact-item">
                        <strong>{{ $tire->code }}</strong>
                        <span>Ultima: {{ $formatDate($tire->latestMeasurement?->measured_at) }}</span>
                    </div>
                @empty
                    <div class="empty-state">Todos os pneus possuem medicao recente.</div>
                @endforelse
            </div>
        </section>
    </div>

    @if($filters['vehicle_id'])
        <div class="tire-report-section">
            <div class="tire-section-header">
                <div>
                    <h2>Pneus instalados no veiculo</h2>
                    <p>Posicoes atuais do veiculo selecionado.</p>
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
        </div>
    @endif

    <div class="tire-report-section">
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
    </div>

    <div class="tire-report-section">
        <div class="tire-section-header">
            <div>
                <h2>Ultimos eventos relevantes</h2>
                <p>Entradas, instalacoes, retiradas, medicoes, recapagens e descartes no periodo.</p>
            </div>
        </div>

        <div class="tire-event-list">
            @forelse($events as $event)
                <div class="tire-event-item status-{{ $event['status'] }}">
                    <span>{{ $formatDate($event['date']) }}</span>
                    <strong>{{ $event['type'] }} - {{ $event['title'] }}</strong>
                    <p>{{ $event['description'] }}</p>
                </div>
            @empty
                <div class="empty-state">Nenhum evento de pneus encontrado no periodo.</div>
            @endforelse
        </div>
    </div>

    @if($context['can_view_cancelled'] && $filters['include_cancelled'])
        <div class="tire-report-section cancelled-section">
            <div class="tire-section-header">
                <div>
                    <h2>Registros cancelados</h2>
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
                    <div class="empty-state">Nenhum registro cancelado no periodo.</div>
                @endforelse
            </div>
        </div>
    @endif
</div>
@endsection
