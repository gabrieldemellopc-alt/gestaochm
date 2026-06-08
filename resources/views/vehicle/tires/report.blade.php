<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">

    <style>
        @page {
            margin: 32px 38px;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            color: #0f172a;
            font-size: 11px;
        }

        .header {
            display: table;
            width: 100%;
            margin-bottom: 26px;
            padding-bottom: 18px;
            border-bottom: 2px solid #e5e7eb;
        }

        .header-left,
        .header-center,
        .header-right {
            display: table-cell;
            vertical-align: middle;
        }

        .header-left {
            width: 22%;
        }

        .header-center {
            width: 50%;
            text-align: center;
        }

        .header-right {
            width: 28%;
            text-align: right;
            color: #64748b;
            font-size: 10px;
            line-height: 1.6;
        }

        .logo-text {
            font-size: 24px;
            font-weight: 800;
            color: #1e2a5a;
        }

        h1 {
            margin: 0;
            font-family: DejaVu Serif, serif;
            font-size: 21px;
            line-height: 1.18;
            letter-spacing: .02em;
            color: #020617;
        }

        .subtitle {
            margin-top: 8px;
            color: #64748b;
            font-size: 12px;
        }

        .section {
            margin-top: 18px;
        }

        .section-title {
            margin-bottom: 8px;
            padding: 8px 10px;
            background: #f1f5f9;
            border-left: 4px solid #22c55e;
            font-size: 12px;
            font-weight: 800;
            color: #0f172a;
        }

        .summary-grid {
            width: 100%;
            border-collapse: collapse;
        }

        .summary-grid td {
            width: 20%;
            padding: 10px;
            border: 1px solid #e5e7eb;
            background: #f8fafc;
            vertical-align: top;
        }

        .summary-grid span {
            display: block;
            color: #64748b;
            font-size: 9px;
            text-transform: uppercase;
            font-weight: 700;
        }

        .summary-grid strong {
            display: block;
            margin-top: 4px;
            font-size: 16px;
            color: #020617;
        }

        table.data-table {
            width: 100%;
            border-collapse: collapse;
        }

        table.data-table th {
            padding: 7px 6px;
            background: #e2e8f0;
            border: 1px solid #cbd5e1;
            color: #0f172a;
            font-size: 9px;
            text-align: left;
            text-transform: uppercase;
        }

        table.data-table td {
            padding: 7px 6px;
            border: 1px solid #e5e7eb;
            vertical-align: top;
        }

        .status {
            display: inline-block;
            padding: 3px 7px;
            border-radius: 12px;
            font-size: 9px;
            font-weight: 700;
        }

        .status.ok {
            color: #166534;
            background: #dcfce7;
        }

        .status.pending,
        .status.empty {
            color: #1e3a8a;
            background: #dbeafe;
        }

        .status.warning {
            color: #854d0e;
            background: #fef3c7;
        }

        .status.danger {
            color: #991b1b;
            background: #fee2e2;
        }

        .muted {
            color: #64748b;
        }

        .footer-note {
            margin-top: 22px;
            padding-top: 10px;
            border-top: 1px solid #e5e7eb;
            color: #64748b;
            font-size: 9px;
            text-align: center;
        }
        
        .report-logo {
        
            width: 90px;
        }
        
    </style>
</head>

<body>

    <div class="header">

        <div class="header-left">
            <div class="logo-text">
                @if($division?->logo)
                
                    <img
                        src="{{ public_path('images/' . $division->logo) }}"
                        class="report-logo"
                    >
                
                @else
                
                    <img
                        src="{{ public_path('images/logo-chm.png') }}"
                        class="report-logo"
                    >
                
                @endif            </div>
        </div>

        <div class="header-center">
            <h1>
                RELATÓRIO DE CONTROLE DE PNEUS
            </h1>

            <div class="subtitle">
                Controle técnico e operacional da frota
            </div>
        </div>

        <div class="header-right">
            <strong>Gerado em:</strong><br>
            {{ now()->format('d/m/Y H:i') }}<br><br>

            <strong>Veículo:</strong><br>
            {{ $vehicle->plate }} - {{ $vehicle->name }}
        </div>

    </div>

    <div class="section">

        <div class="section-title">
            Resumo do veículo
        </div>

        <table class="summary-grid">
            <tr>
                <td>
                    <span>KM atual</span>
                    <strong>{{ number_format($vehicle->current_km ?? 0, 0, ',', '.') }}</strong>
                </td>

                <td>
                    <span>Posições</span>
                    <strong>{{ $summary['positions'] }}</strong>
                </td>

                <td>
                    <span>Instalados</span>
                    <strong>{{ $summary['installed'] }}</strong>
                </td>

                <td>
                    <span>Sem medição</span>
                    <strong>{{ $summary['without_measurement'] }}</strong>
                </td>

                <td>
                    <span>Alertas</span>
                    <strong>{{ $summary['warning'] + $summary['danger'] }}</strong>
                </td>
            </tr>
        </table>

    </div>

    <div class="section">

        <div class="section-title">
            Mapa atual de pneus
        </div>

        <table class="data-table">
            <thead>
                <tr>
                    <th>Posição</th>
                    <th>Pneu</th>
                    <th>Marca / Modelo</th>
                    <th>Sulco inicial</th>
                    <th>Último sulco</th>
                    <th>KM inst.</th>
                    <th>Última medição</th>
                    <th>Status</th>
                </tr>
            </thead>

            <tbody>
                @foreach($positions as $item)
                    @php
                        $position = $item['position'];
                        $installation = $item['installation'];
                        $tire = $item['tire'];
                        $measurement = $item['latest_measurement'];
                        $status = $item['status'];

                        $statusLabel = match($status) {
                            'empty' => 'Sem pneu',
                            'pending' => 'Sem medição',
                            'warning' => 'Atenção',
                            'danger' => 'Crítico',
                            default => 'OK',
                        };
                    @endphp

                    <tr>
                        <td>
                            <strong>{{ $position->code }}</strong><br>
                            <span class="muted">{{ $position->label }}</span>
                        </td>

                        <td>
                            {{ $tire?->code ?? '--' }}
                        </td>

                        <td>
                            {{ $tire?->brand ?? '--' }}
                            {{ $tire?->model ? ' / ' . $tire->model : '' }}
                        </td>

                        <td>
                            {{ $tire?->initial_tread_depth ?? '--' }}{{ $tire ? ' mm' : '' }}
                        </td>

                        <td>
                            {{ $measurement?->minimum_tread ?? '--' }}{{ $measurement ? ' mm' : '' }}
                        </td>

                        <td>
                            {{ $installation?->installed_km ? number_format($installation->installed_km, 0, ',', '.') : '--' }}
                        </td>

                        <td>
                            {{ optional($measurement?->measured_at)->format('d/m/Y') ?? '--' }}
                        </td>

                        <td>
                            <span class="status {{ $status }}">
                                {{ $statusLabel }}
                            </span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

    </div>

    <div class="section">

        <div class="section-title">
            Histórico de instalações e movimentações
        </div>

        <table class="data-table">
            <thead>
                <tr>
                    <th>Instalação</th>
                    <th>Pneu</th>
                    <th>Posição</th>
                    <th>KM inst.</th>
                    <th>Remoção</th>
                    <th>Motivo</th>
                    <th>Responsável</th>
                </tr>
            </thead>

            <tbody>
                @forelse($installations as $installation)
                    <tr>
                        <td>
                            {{ optional($installation->installed_at)->format('d/m/Y') ?? '--' }}
                        </td>

                        <td>
                            {{ $installation->tire?->code ?? '--' }}
                        </td>

                        <td>
                            {{ $installation->position_code }}
                        </td>

                        <td>
                            {{ $installation->installed_km ? number_format($installation->installed_km, 0, ',', '.') : '--' }}
                        </td>

                        <td>
                            {{ optional($installation->removed_at)->format('d/m/Y') ?? '--' }}
                        </td>

                        <td>
                            {{ $installation->removal_reason ?? '--' }}
                        </td>

                        <td>
                            {{ $installation->creator?->name ?? '--' }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7">
                            Nenhuma instalação registrada.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

    </div>

    <div class="section">

        <div class="section-title">
            Histórico de medições de sulco
        </div>

        <table class="data-table">
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Posição</th>
                    <th>Pneu</th>
                    <th>KM</th>
                    <th>Sulco</th>
                    <th>Observação</th>
                    <th>Usuário</th>
                </tr>
            </thead>

            <tbody>
                @forelse($measurements as $measurement)
                    <tr>
                        <td>
                            {{ optional($measurement->measured_at)->format('d/m/Y') }}
                        </td>

                        <td>
                            {{ $measurement->position_code }}
                        </td>

                        <td>
                            {{ $measurement->tire?->code ?? '--' }}
                        </td>

                        <td>
                            {{ $measurement->vehicle_km ? number_format($measurement->vehicle_km, 0, ',', '.') : '--' }}
                        </td>

                        <td>
                            {{ $measurement->minimum_tread ?? '--' }} mm
                        </td>

                        <td>
                            {{ $measurement->notes ?? '--' }}
                        </td>

                        <td>
                            {{ $measurement->user?->name ?? '--' }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7">
                            Nenhuma medição registrada.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

    </div>

    <div class="footer-note">
        Relatório gerado automaticamente pelo sistema CHM.
    </div>

</body>
</html>