<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">

<style>
    @page {
        margin: 24px 28px 34px;
    }

    * {
        box-sizing: border-box;
    }

    body {
        margin: 0;
        font-family: DejaVu Sans, sans-serif;
        color: #25324a;
        font-size: 9px;
    }

    .header {
        width: 100%;
        border-bottom: 2px solid #29364f;
        padding-bottom: 12px;
        margin-bottom: 14px;
    }

    .header-table {
        width: 100%;
        border-collapse: collapse;
    }

    .logo-cell {
        width: 150px;
        vertical-align: middle;
    }

    .logo {
        max-width: 125px;
        max-height: 54px;
    }

    .title-cell {
        vertical-align: middle;
    }

    .title-cell h1 {
        margin: 0 0 4px;
        font-size: 20px;
        color: #202a40;
    }

    .title-cell p {
        margin: 0;
        color: #667085;
        font-size: 9px;
    }

    .meta-cell {
        width: 210px;
        text-align: right;
        vertical-align: middle;
        line-height: 1.55;
        color: #667085;
    }

    .summary {
        width: 100%;
        border-collapse: separate;
        border-spacing: 6px 0;
        margin: 0 -6px 14px;
    }

    .summary td {
        border: 1px solid #d6dce7;
        border-radius: 5px;
        padding: 9px 10px;
        vertical-align: top;
    }

    .summary small {
        display: block;
        text-transform: uppercase;
        font-size: 7px;
        font-weight: bold;
        color: #7a8699;
        margin-bottom: 4px;
    }

    .summary strong {
        font-size: 13px;
        color: #202a40;
    }

    .filters {
        margin-bottom: 12px;
        padding: 8px 10px;
        background: #f4f6f9;
        border: 1px solid #e0e5ec;
        border-radius: 4px;
        color: #5e6b80;
        line-height: 1.6;
    }

    .filters strong {
        color: #344054;
    }

    table.report {
        width: 100%;
        border-collapse: collapse;
    }

    .report th {
        background: #202a40;
        color: #fff;
        text-transform: uppercase;
        font-size: 7px;
        letter-spacing: .04em;
        padding: 7px 6px;
        text-align: left;
    }

    .report td {
        border-bottom: 1px solid #e2e6ed;
        padding: 7px 6px;
        vertical-align: top;
    }

    .report tr.cancelled td {
        color: #8b95a6;
        background: #fafafa;
    }

    .muted {
        color: #7a8699;
        font-size: 8px;
    }

    .status {
        font-weight: bold;
    }

    .status.cancelled {
        color: #a33b43;
    }

    .status.active {
        color: #29744c;
    }

    .footer {
        position: fixed;
        bottom: -20px;
        left: 0;
        right: 0;
        text-align: center;
        color: #8a94a6;
        font-size: 7px;
        border-top: 1px solid #e0e5ec;
        padding-top: 5px;
    }
</style>
</head>

<body>

@php
    $logoPath = null;

    if ($division?->logo && file_exists(public_path('images/'.$division->logo))) {
        $logoPath = public_path('images/'.$division->logo);
    } elseif (file_exists(public_path('images/logo-chm.png'))) {
        $logoPath = public_path('images/logo-chm.png');
    }

    $start = request('start_date');
    $end = request('end_date');

    $periodLabel = $start || $end
        ? (($start ? \Carbon\Carbon::parse($start)->format('d/m/Y') : 'Início')
            .' a '
            .($end ? \Carbon\Carbon::parse($end)->format('d/m/Y') : 'Hoje'))
        : 'Todo o período';
@endphp

<div class="header">
    <table class="header-table">
        <tr>
            <td class="logo-cell">
                @if($logoPath)
                    <img src="{{ $logoPath }}" class="logo">
                @endif
            </td>

            <td class="title-cell">
                <h1>Relatório de Abastecimentos</h1>
                <p>
                    Histórico completo de saídas de combustível
                    @if($location)
                        · Unidade {{ $location->name }}
                    @endif
                </p>
            </td>

            <td class="meta-cell">
                <strong>Gerado por:</strong>
                {{ $generatedBy?->name ?? 'Usuário não identificado' }}<br>

                <strong>Gerado em:</strong>
                {{ $generatedAt->format('d/m/Y H:i') }}
            </td>
        </tr>
    </table>
</div>

<table class="summary">
    <tr>
        <td>
            <small>Abastecimentos</small>
            <strong>{{ number_format($summary['fillings_count'], 0, ',', '.') }}</strong>
        </td>

        <td>
            <small>Veículos abastecidos</small>
            <strong>{{ number_format($summary['vehicles_count'], 0, ',', '.') }}</strong>
        </td>

        <td>
            <small>Volume total</small>
            <strong>{{ number_format($summary['liters_total'], 3, ',', '.') }} L</strong>
        </td>

        @if($permissions['view_costs'])
        <td>
            <small>Valor aproximado</small>
            <strong>R$ {{ number_format((float) $summary['cost_total'], 2, ',', '.') }}</strong>
        </td>
        @endif
    </tr>
</table>

<div class="filters">
    <strong>Período:</strong> {{ $periodLabel }}

    @if($vehicle)
        &nbsp;&nbsp;|&nbsp;&nbsp;
        <strong>Veículo:</strong>
        {{ $vehicle->name }}
        @if($vehicle->plate)
            · {{ $vehicle->plate }}
        @endif
    @endif

    &nbsp;&nbsp;|&nbsp;&nbsp;
    <strong>Vínculo:</strong>
    {{
        match($fleetRelation) {
            'internal' => 'Interno',
            'rented' => 'Alugado',
            'aggregated' => 'Agregado',
            default => 'Todos',
        }
    }}

    @if(request('status'))
        &nbsp;&nbsp;|&nbsp;&nbsp;
        <strong>Status:</strong>
        {{ request('status') === 'active' ? 'Realizados' : 'Cancelados' }}
    @endif

    @if(request('source'))
        &nbsp;&nbsp;|&nbsp;&nbsp;
        <strong>Origem:</strong>
        {{ request('source') === 'internal_tank' ? 'Tanque da unidade' : 'Posto externo' }}
    @endif
</div>

<table class="report">
    <thead>
        <tr>
            <th style="width: 9%">Data</th>
            <th style="width: 14%">Veículo</th>
            <th style="width: 18%">Origem / Produto</th>
            <th style="width: 9%">Litros</th>
            <th style="width: 10%">KM</th>
            <th style="width: 12%">Responsável</th>

            @if($permissions['view_costs'])
                <th style="width: 10%">Valor</th>
            @endif

            <th>Status</th>
        </tr>
    </thead>

    <tbody>
        @forelse($fillings as $filling)
            <tr class="{{ $filling->cancelled_at ? 'cancelled' : '' }}">
                <td>
                    {{ $filling->filled_at?->format('d/m/Y') }}<br>
                    <span class="muted">{{ $filling->filled_at?->format('H:i') }}</span>
                </td>

                <td>
                    <strong>{{ $filling->vehicle?->name ?? '—' }}</strong><br>
                    <span class="muted">
                        {{ $filling->vehicle?->plate ?: ($filling->vehicle?->asset_code ?: '—') }}
                    </span>
                </td>

                <td>
                    {{ $filling->source_label }}<br>
                    <span class="muted">
                        {{ $filling->location_label }}
                        @if($filling->product)
                            · {{ $filling->product->name }}
                        @endif
                    </span>
                </td>

                <td>
                    {{ number_format((float) $filling->quantity_liters, 3, ',', '.') }} L
                </td>

                <td>
                    @if($filling->vehicle_km !== null)
                        {{ number_format((float) $filling->vehicle_km, 0, ',', '.') }} km
                    @else
                        —
                    @endif
                </td>

                <td>
                    {{ $filling->responsible?->name ?? '—' }}
                </td>

                @if($permissions['view_costs'])
                    <td>
                        R$ {{ number_format((float) $filling->reporting_total_cost, 2, ',', '.') }}
                    </td>
                @endif

                <td>
                    @if($filling->cancelled_at)
                        <span class="status cancelled">Cancelado</span>

                        @if($filling->replaced_by_filling_id)
                            <br>
                            <span class="muted">
                                Substituído pelo #{{ $filling->replaced_by_filling_id }}
                            </span>
                        @elseif($filling->cancel_reason)
                            <br>
                            <span class="muted">{{ $filling->cancel_reason }}</span>
                        @endif
                    @else
                        <span class="status active">Realizado</span>

                        @if($filling->replaces_filling_id)
                            <br>
                            <span class="muted">
                                Correção do #{{ $filling->replaces_filling_id }}
                            </span>
                        @endif
                    @endif
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="{{ $permissions['view_costs'] ? 8 : 7 }}">
                    Nenhum abastecimento encontrado para os filtros informados.
                </td>
            </tr>
        @endforelse
    </tbody>
</table>

<div class="footer">
    Relatório gerado automaticamente pelo sistema CHM.
    Abastecimentos cancelados não integram os totais do resumo.
</div>

</body>
</html>
