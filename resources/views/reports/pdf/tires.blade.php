<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #111827;
            font-size: 10px;
        }

        h1, h2, h3, p {
            margin: 0;
        }

        .header {
            border-bottom: 2px solid #991b1b;
            margin-bottom: 16px;
            padding-bottom: 10px;
        }

        .title {
            color: #0f172a;
            font-size: 22px;
            font-weight: 700;
        }

        .subtitle {
            color: #475569;
            margin-top: 4px;
        }

        .kpi-table {
            width: 100%;
            margin: 12px 0 16px;
            border-collapse: collapse;
        }

        .kpi-table td {
            width: 16.66%;
            border: 1px solid #cbd5e1;
            padding: 8px;
            background: #f8fafc;
        }

        .label {
            display: block;
            color: #64748b;
            font-size: 8px;
            text-transform: uppercase;
        }

        .value {
            display: block;
            margin-top: 4px;
            color: #0f172a;
            font-size: 16px;
            font-weight: 700;
        }

        .section {
            margin-top: 16px;
            page-break-inside: avoid;
        }

        .section h2 {
            color: #0f172a;
            font-size: 14px;
            margin-bottom: 7px;
        }

        table.data {
            width: 100%;
            border-collapse: collapse;
        }

        table.data th,
        table.data td {
            border: 1px solid #d1d5db;
            padding: 5px 6px;
            text-align: left;
            vertical-align: top;
        }

        table.data th {
            background: #e2e8f0;
            color: #0f172a;
            font-size: 8px;
            text-transform: uppercase;
        }

        .muted {
            color: #64748b;
        }

        .danger {
            color: #991b1b;
            font-weight: 700;
        }
    </style>
</head>
<body>
@php
    $formatDate = fn ($date) => $date ? \Carbon\Carbon::parse($date)->format('d/m/Y') : '-';
    $formatDepth = fn ($value) => $value !== null ? number_format((float) $value, 1, ',', '.') . ' mm' : '-';
@endphp

<div class="header">
    <div class="title">Relatorio de Pneus</div>
    <div class="subtitle">
        Unidade: {{ $context['location']->name ?? '-' }} |
        Divisao: {{ $context['division']->name ?? '-' }} |
        Periodo: {{ $formatDate($filters['start_date']) }} a {{ $formatDate($filters['end_date']) }}
    </div>
    @if($filters['period_error'])
        <div class="subtitle danger">{{ $filters['period_error'] }}</div>
    @endif
</div>

<table class="kpi-table">
    <tr>
        <td><span class="label">Total</span><span class="value">{{ $summary['total'] }}</span></td>
        <td><span class="label">Instalados</span><span class="value">{{ $summary['installed'] }}</span></td>
        <td><span class="label">Disponiveis</span><span class="value">{{ $summary['available'] }}</span></td>
        <td><span class="label">Manutencao</span><span class="value">{{ $summary['maintenance'] }}</span></td>
        <td><span class="label">Criticos</span><span class="value">{{ $summary['critical'] }}</span></td>
        <td><span class="label">Descartados</span><span class="value">{{ $summary['discarded'] }}</span></td>
    </tr>
</table>

<div class="section">
    <h2>Recapagens</h2>
    <table class="data">
        <tr>
            <th>Sem recapagem</th>
            <th>R1</th>
            <th>R2</th>
            <th>R3+</th>
        </tr>
        <tr>
            <td>{{ $retreadSummary['none'] }}</td>
            <td>{{ $retreadSummary['r1'] }}</td>
            <td>{{ $retreadSummary['r2'] }}</td>
            <td>{{ $retreadSummary['r3plus'] }}</td>
        </tr>
    </table>
</div>

<div class="section">
    <h2>Inventario filtrado</h2>
    <table class="data">
        <thead>
            <tr>
                <th>Codigo</th>
                <th>Status</th>
                <th>Veiculo</th>
                <th>Marca/Modelo</th>
                <th>R</th>
                <th>Sulco</th>
            </tr>
        </thead>
        <tbody>
            @forelse($tires as $tire)
                <tr>
                    <td>{{ $tire->code }}</td>
                    <td>{{ $tire->status }}</td>
                    <td>{{ $tire->activeInstallation?->vehicle?->plate ?? '-' }}</td>
                    <td>{{ trim(($tire->brand ?: '-') . ' / ' . ($tire->model ?: '-')) }}</td>
                    <td>{{ (int) $tire->retreads_count }}</td>
                    <td>{{ $formatDepth($tire->current_tread_depth) }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted">Nenhum pneu encontrado.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="section">
    <h2>Eventos do periodo</h2>
    <table class="data">
        <thead>
            <tr>
                <th>Data</th>
                <th>Tipo</th>
                <th>Registro</th>
                <th>Descricao</th>
            </tr>
        </thead>
        <tbody>
            @forelse($events as $event)
                <tr>
                    <td>{{ $formatDate($event['date']) }}</td>
                    <td>{{ $event['type'] }}</td>
                    <td>{{ $event['title'] }}</td>
                    <td>{{ $event['description'] }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="muted">Nenhum evento no periodo.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="section">
    <h2>Alertas</h2>
    <table class="data">
        <thead>
            <tr>
                <th>Tipo</th>
                <th>Codigo</th>
                <th>Veiculo</th>
                <th>Sulco atual</th>
                <th>Ultima medicao</th>
            </tr>
        </thead>
        <tbody>
            @forelse($criticalTires as $tire)
                <tr>
                    <td class="danger">Sulco critico</td>
                    <td>{{ $tire->code }}</td>
                    <td>{{ $tire->activeInstallation?->vehicle?->plate ?? '-' }}</td>
                    <td>{{ $formatDepth($tire->current_tread_depth) }}</td>
                    <td>{{ $formatDate($tire->latestMeasurement?->measured_at) }}</td>
                </tr>
            @empty
            @endforelse

            @forelse($noRecentMeasurements as $tire)
                <tr>
                    <td>Sem medicao recente</td>
                    <td>{{ $tire->code }}</td>
                    <td>{{ $tire->activeInstallation?->vehicle?->plate ?? '-' }}</td>
                    <td>{{ $formatDepth($tire->current_tread_depth) }}</td>
                    <td>{{ $formatDate($tire->latestMeasurement?->measured_at) }}</td>
                </tr>
            @empty
            @endforelse
        </tbody>
    </table>
</div>

@if($context['can_view_cancelled'] && $filters['include_cancelled'])
    <div class="section">
        <h2>Cancelados</h2>
        <table class="data">
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Tipo</th>
                    <th>Registro</th>
                    <th>Motivo</th>
                    <th>Usuario</th>
                </tr>
            </thead>
            <tbody>
                @forelse($cancelledRecords as $record)
                    <tr>
                        <td>{{ $formatDate($record['date']) }}</td>
                        <td>{{ $record['type'] }}</td>
                        <td>{{ $record['title'] }}</td>
                        <td>{{ $record['reason'] ?: '-' }}</td>
                        <td>{{ $record['user'] ?: '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">Nenhum cancelado no periodo.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endif
</body>
</html>
