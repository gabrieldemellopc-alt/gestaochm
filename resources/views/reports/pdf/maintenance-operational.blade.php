<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #111827; font-size: 10px; }
        h1 { margin: 0 0 4px; font-size: 20px; }
        .meta { color: #64748b; margin-bottom: 18px; }
        .notice { margin: 12px 0; padding: 9px; background: #f1f5f9; color: #475569; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th { background: #111827; color: #fff; padding: 8px; text-align: left; }
        td { padding: 8px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        .cancelled { color: #991b1b; }
        .footer { margin-top: 24px; color: #94a3b8; text-align: center; }
    </style>
</head>
<body>
    <h1>Relatório operacional de manutenções</h1>
    <div class="meta">
        {{ $division?->name ?? '-' }} — {{ $location?->name ?? '-' }}<br>
        Período: {{ \Carbon\Carbon::parse($startDate)->format('d/m/Y') }} a {{ \Carbon\Carbon::parse($endDate)->format('d/m/Y') }}
    </div>

    <div class="notice">Valores monetários restritos para este perfil.</div>

    <div class="notice">
        <strong>Ordens de manutenção</strong><br>
        {{ $maintenanceCount }} registros<br>
        {{ $costComposition['service_count'] }} serviços registrados
    </div>

    <h2>Composição dos custos</h2>
    <table>
        <tbody>
            <tr><td>Serviços</td><td>{{ $costComposition['service_count'] }} registros</td><td>R$ {{ number_format($costComposition['service_total'], 2, ',', '.') }}</td></tr>
            @if($costComposition['extra_cost_count'] > 0)
                <tr><td>Custos avulsos</td><td>{{ $costComposition['extra_cost_count'] }} registros</td><td>R$ {{ number_format($costComposition['extra_cost_total'], 2, ',', '.') }}</td></tr>
            @endif
            <tr><td><strong>Total consolidado</strong></td><td></td><td><strong>R$ {{ number_format($costComposition['grand_total'], 2, ',', '.') }}</strong></td></tr>
        </tbody>
    </table>

    <table>
        <thead>
            <tr>
                <th>Ordem</th>
                <th>Entrada</th>
                <th>Saída</th>
                <th>Veículo</th>
                <th>Placa</th>
                <th>Composição</th>
                <th>Status</th>
                <th>Valor</th>
            </tr>
        </thead>
        <tbody>
            @forelse($maintenances as $maintenance)
                <tr>
                    <td>#{{ $maintenance->id }}</td>
                    <td>{{ optional($maintenance->started_at)->format('d/m/Y H:i') }}</td>
                    <td>{{ optional($maintenance->finished_at)->format('d/m/Y H:i') ?: 'Em aberto' }}</td>
                    <td>{{ $maintenance->vehicle->name ?? '-' }}</td>
                    <td>{{ $maintenance->vehicle->plate ?? '-' }}</td>
                    <td>
                        @php
                            $services = $maintenance->items;
                            $extraCosts = $maintenance->extraCosts;
                        @endphp

                        @if($services->isNotEmpty())
                            <div>
                                {{ $services->count() === 1 ? 'Serviço' : 'Serviços' }}<br>
                                {{ $services->count() }} · R$ {{ number_format($services->sum('total_cost'), 2, ',', '.') }}
                            </div>
                        @endif
                        @if($extraCosts->isNotEmpty())
                            <div>
                                {{ $extraCosts->count() === 1 ? 'Custo avulso' : 'Custos avulsos' }}<br>
                                {{ $extraCosts->count() }} · R$ {{ number_format($extraCosts->sum('amount'), 2, ',', '.') }}
                            </div>
                        @endif
                    </td>
                    <td>{{ $maintenance->workflow_status ?? '-' }}</td>
                    <td>R$ {{ number_format($maintenance->total_cost, 2, ',', '.') }}</td>
                </tr>
            @empty
                <tr><td colspan="8">Nenhuma manutenção encontrada no período.</td></tr>
            @endforelse
        </tbody>
    </table>

    @if(($canViewCancelled ?? false) && $cancelledMaintenancesRaw->isNotEmpty())
        <h2 class="cancelled">Registros cancelados</h2>
        <p>Exibidos apenas para conferência e fora dos totais operacionais.</p>
        <table>
            <thead>
                <tr>
                    <th>Ordem</th>
                    <th>Cancelada em</th>
                    <th>Veículo</th>
                    <th>Cancelada por</th>
                    <th>Motivo</th>
                </tr>
            </thead>
            <tbody>
                @foreach($cancelledMaintenancesRaw as $maintenance)
                    <tr>
                        <td>#{{ $maintenance->id }}</td>
                        <td>{{ optional($maintenance->cancelled_at)->format('d/m/Y H:i') }}</td>
                        <td>{{ $maintenance->vehicle->name ?? '-' }} — {{ $maintenance->vehicle->plate ?? '-' }}</td>
                        <td>{{ $maintenance->canceller?->name ?? '-' }}</td>
                        <td>{{ $maintenance->cancel_reason ?? '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="footer">Relatório gerado em {{ now()->format('d/m/Y H:i') }}</div>
</body>
</html>
