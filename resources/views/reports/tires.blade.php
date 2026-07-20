@extends('layouts.app')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/pages/reports.css') }}?v=5">
@endpush

@section('content')
@php
    $reportPermissions = $reportPermissions ?? [];
    $canExportReportPdf = $reportPermissions['reports.export_pdf'] ?? true;
    $canExportReportExcel = $reportPermissions['reports.export_excel'] ?? true;
@endphp
@php
    $statusLabels = [
        'available' => 'Disponivel',
        'installed' => 'Instalado',
        'maintenance' => 'Manutencao',
        'discarded' => 'Descartado',
    ];

    $formatDate = fn ($date) => $date ? \Carbon\Carbon::parse($date)->format('d/m/Y') : '-';
    $formatDepth = fn ($value) => $value !== null ? number_format((float) $value, 1, ',', '.') . ' mm' : '-';
    $currentQuery = request()->query();
@endphp

<div class="reports-page reports-tires-page tire-summary-dashboard">
    <div class="reports-hero reports-tires-hero">
        <div>
            <div class="reports-hero-badge">Central de Pneus</div>
            <h1>Painel de Pneus</h1>
            <p>
                Resumo operacional da unidade {{ $context['location']->name ?? '-' }}.
                Use as ações para abrir o relatório completo ou exportar.
            </p>
        </div>

        <div class="tire-action-row">
            <a href="{{ route('reports.tires.full', $currentQuery) }}" class="report-module-button">
                Visualizar relatorio completo
            </a>
@if($canExportReportPdf)
<a href="{{ route('reports.tires.export.pdf', $currentQuery) }}" class="report-module-button secondary">
                Exportar PDF
            </a>
@endif
@if($canExportReportExcel)
<a href="{{ route('reports.tires.export.excel', $currentQuery) }}" class="report-module-button secondary">
                Exportar Excel
            </a>
@endif
        </div>
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
                    Cancelados no relatorio completo
                </label>
            @endif

            <button type="submit" class="report-module-button">Aplicar filtros</button>
        </div>

        <div class="tire-filter-note">
            O painel mostra somente resumo. Inventario completo, eventos e cancelamentos ficam na visão detalhada/PDF/Excel.
        </div>
    </form>

    @if($filters['period_error'])
        <div class="tire-report-alert">{{ $filters['period_error'] }}</div>
    @endif

    <section class="tire-report-section">
        <div class="tire-section-header">
            <div>
                <span class="tire-section-kicker">Resumo atual</span>
                <h2>Inventario da unidade</h2>
                <p>Estado atual dos pneus, independente do periodo selecionado.</p>
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

    <div class="tire-report-columns">
        <section class="tire-report-section">
            <div class="tire-section-header">
                <div>
                    <h2>Top 5 pneus criticos</h2>
                    <p>Sulco atual abaixo ou igual ao limite critico.</p>
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
                    <p>Top 5 pneus com medicao mais antiga ou sem registro.</p>
                </div>
            </div>

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
        </section>
    </div>

    <section class="tire-report-section">
        <div class="tire-section-header">
            <div>
                <h2>Resumo por veiculo</h2>
                <p>Primeiros veiculos com pneus atualmente instalados. A lista completa fica no relatorio detalhado.</p>
            </div>
        </div>

        <div class="vehicle-tire-groups compact-groups">
            @forelse($tiresByVehicle->take(5) as $group)
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
