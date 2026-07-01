<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">

    <style>
        body {
            font-family: DejaVu Sans;
            color: #111827;
            font-size: 12px;
        }

        .report-header {
            width: 100%;
            display: table;
            margin-bottom: 28px;
            border-bottom: 2px solid #e5e7eb;
            padding-bottom: 18px;
        }

        .report-header-left,
        .report-header-center,
        .report-header-right {
            display: table-cell;
            vertical-align: middle;
        }

        .report-header-left {
            width: 120px;
        }

        .report-header-center {
            text-align: center;
        }

        .report-header-right {
            width: 180px;
            text-align: right;
            font-size: 11px;
            color: #6b7280;
        }

        .report-logo {
            width: 90px;
        }

        .report-title {
            font-size: 21px;
            font-weight: 800;
            color: #0f172a;
            line-height: 1.2;
        }

        .report-subtitle {
            margin-top: 8px;
            font-size: 12px;
            color: #64748b;
        }

        .section-title {
            font-size: 15px;
            font-weight: bold;
            color: #0f172a;
            margin: 24px 0 12px;
        }

        .info-grid {
            width: 100%;
            display: table;
            border-spacing: 12px 0;
            margin-bottom: 20px;
        }

        .info-box {
            display: table-cell;
            width: 33.33%;
            border: 1px solid #dbe2ea;
            border-radius: 10px;
            padding: 12px 14px;
            vertical-align: top;
        }

        .info-label {
            font-size: 10px;
            color: #64748b;
            text-transform: uppercase;
            font-weight: bold;
        }

        .info-value {
            margin-top: 5px;
            font-size: 14px;
            font-weight: bold;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        th {
            background: #111827;
            color: white;
            padding: 10px;
            text-align: left;
            font-size: 10px;
        }

        td {
            padding: 10px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 10px;
            vertical-align: top;
        }

        .muted {
            color: #64748b;
        }

        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 999px;
            font-size: 10px;
            font-weight: bold;
        }

        .badge-open {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .badge-closed {
            background: #dcfce7;
            color: #166534;
        }

        .badge-cancelled {
            background: #fee2e2;
            color: #b91c1c;
        }

        .timeline-item {
            border-left: 3px solid #dbe2ea;
            padding-left: 12px;
            margin-bottom: 14px;
        }

        .timeline-item strong {
            display: block;
        }

        .timeline-item span {
            display: block;
            color: #64748b;
            font-size: 10px;
            margin-top: 2px;
        }

        .total-box {
            margin-top: 22px;
            padding: 16px;
            border: 1px solid #dbe2ea;
            border-radius: 10px;
            text-align: right;
        }

        .total-box span {
            color: #64748b;
            font-size: 11px;
        }

        .total-box strong {
            display: block;
            margin-top: 4px;
            font-size: 20px;
        }

        .footer {
            margin-top: 34px;
            padding-top: 12px;
            border-top: 1px solid #dbe2ea;
            text-align: center;
            font-size: 10px;
            color: #94a3b8;
        }
        
        .section-title { margin: 18px 0 10px; }
            .info-grid { margin-bottom: 14px; }
            td { padding: 8px; }
    </style>
</head>

<body>
    @php
        $stockItemIds = $maintenance->items
            ->flatMap(fn ($item) => $item->values)
            ->filter(fn ($value) => $value->field?->field_type === 'stock_item' && $value->value)
            ->pluck('value')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    
        $stockItemsById = \App\Models\StockItem::query()
            ->whereIn('id', $stockItemIds)
            ->get()
            ->keyBy('id');
        $workflowLabels = [
            'open' => 'Aberta',
            'closed' => 'Encerrada',
            'cancelled' => 'Cancelada',
        ];

        $workflowClass = [
            'open' => 'badge-open',
            'closed' => 'badge-closed',
            'cancelled' => 'badge-cancelled',
        ];

        $serviceStatuses = \App\Services\MaintenanceService::serviceStatuses();

        $startedAt = $maintenance->started_at;
        $finishedAt = $maintenance->finished_at;

        $downtime = ($startedAt && $finishedAt)
            ? $startedAt->diffForHumans($finishedAt, true)
            : ($startedAt ? $startedAt->diffForHumans(now(), true) : '—');
    @endphp

    <div class="report-header">
        <div class="report-header-left">
            @if($maintenance->vehicle?->division?->logo)
                <img src="{{ public_path('images/' . $maintenance->vehicle->division->logo) }}" class="report-logo">
            @else
                <img src="{{ public_path('images/logo-chm.png') }}" class="report-logo">
            @endif
        </div>

        <div class="report-header-center">
            <div class="report-title">
                ORDEM DE MANUTENÇÃO #{{ $maintenance->id }}
            </div>

            <div class="report-subtitle">
                Dossiê técnico e operacional da parada do veículo
            </div>
        </div>

        <div class="report-header-right">
            <strong>Gerado em:</strong><br>
            {{ now()->format('d/m/Y H:i') }}
        </div>
    </div>

    <div class="info-grid">
        <div class="info-box">
            <div class="info-label">Veículo</div>
            <div class="info-value">{{ $maintenance->vehicle->name ?? '-' }}</div>
            <div class="muted">
                {{ $maintenance->vehicle->plate ?? '-' }}
                —
                {{ $maintenance->vehicle->brand ?? '' }}
                {{ $maintenance->vehicle->model ?? '' }}
            </div>
        </div>

        <div class="info-box">
            <div class="info-label">Status da ordem</div>
            <div class="info-value">
                <span class="badge {{ $workflowClass[$maintenance->workflow_status] ?? '' }}">
                    {{ $workflowLabels[$maintenance->workflow_status] ?? $maintenance->workflow_status }}
                </span>
            </div>
            <div class="muted">
                {{ $serviceStatuses[$maintenance->service_status] ?? 'Status operacional não informado' }}
            </div>
        </div>

        <div class="info-box">
            <div class="info-label">Tempo parado</div>
            <div class="info-value">{{ $downtime }}</div>
            <div class="muted">
                Entrada: {{ optional($maintenance->started_at)->format('d/m/Y H:i') ?? '-' }}<br>
                Saída: {{ optional($maintenance->finished_at)->format('d/m/Y H:i') ?? 'Em aberto' }}
            </div>
        </div>
    </div>

    <div class="info-grid">
        <div class="info-box">
            <div class="info-label">Abertura</div>
            <div class="info-value">{{ $maintenance->opener?->name ?? '-' }}</div>
        </div>

        <div class="info-box">
            <div class="info-label">Encerramento</div>
            <div class="info-value">{{ $maintenance->closer?->name ?? '-' }}</div>
        </div>

        <div class="info-box">
            <div class="info-label">Custo total</div>
            <div class="info-value">
                R$ {{ number_format($maintenance->total_cost ?? 0, 2, ',', '.') }}
            </div>
        </div>
    </div>

    @if($maintenance->notes || $maintenance->closure_notes || $maintenance->cancel_reason)
        <div class="section-title">Observações</div>

        <table>
            <tbody>
                @if($maintenance->notes)
                    <tr>
                        <td><strong>Observações iniciais</strong></td>
                        <td>{{ $maintenance->notes }}</td>
                    </tr>
                @endif

                @if($maintenance->closure_notes)
                    <tr>
                        <td><strong>Observações de encerramento</strong></td>
                        <td>{{ $maintenance->closure_notes }}</td>
                    </tr>
                @endif

                @if($maintenance->cancel_reason)
                    <tr>
                        <td><strong>Motivo do cancelamento</strong></td>
                        <td>{{ $maintenance->cancel_reason }}</td>
                    </tr>
                @endif
            </tbody>
        </table>
    @endif

    <div class="section-title">Procedimentos executados</div>

    <table>
        <thead>
            <tr>
                <th>Procedimento</th>
                <th>Tipo</th>
                <th>Data</th>
                <th>KM</th>
                <th>HR</th>
                <th>Fornecedor</th>
                <th>Valor</th>
            </tr>
        </thead>

        <tbody>
            @forelse($maintenance->items as $item)
                <tr>
                    <td>{{ $item->procedure->name ?? '-' }}</td>
                    <td>{{ $item->maintenance_type === 'internal' ? 'Interna' : 'Terceirizada' }}</td>
                    <td>{{ optional($item->performed_at)->format('d/m/Y') ?? '-' }}</td>
                    <td>{{ $item->performed_km ? number_format($item->performed_km, 0, ',', '.') : '-' }}</td>
                    <td>{{ $item->performed_hours ? number_format($item->performed_hours, 0, ',', '.') : '-' }}</td>
                    <td>{{ $item->provider_name ?? '-' }}</td>
                    <td><strong>R$ {{ number_format($item->total_cost ?? 0, 2, ',', '.') }}</strong></td>
                </tr>

                @if($item->values->count())
                    <tr>
                        <td colspan="7">
                            <strong>Campos/itens informados:</strong>
                            @foreach($item->values as $value)
                                @if($value->value)
                                    <div class="muted">
                                        {{ $value->field->label ?? 'Campo' }}:
                                        
                                        @if($value->field?->field_type === 'stock_item')
                                            {{ $stockItemsById[(int) $value->value]->name ?? $value->value }}
                                        @else
                                            {{ $value->value }}
                                        @endif
                                        
                                        @if($value->quantity)
                                            — qtd. {{ number_format($value->quantity, 2, ',', '.') }}
                                        @endif
                                    </div>
                                @endif
                            @endforeach
                        </td>
                    </tr>
                @endif
            @empty
                <tr>
                    <td colspan="7">Nenhum procedimento executado nesta ordem.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="section-title">Custos avulsos</div>

    <table>
        <thead>
            <tr>
                <th>Descrição</th>
                <th>Lançado por</th>
                <th>Data</th>
                <th>Valor</th>
            </tr>
        </thead>

        <tbody>
            @forelse($maintenance->extraCosts as $extraCost)
                <tr>
                    <td>{{ $extraCost->description }}</td>
                    <td>{{ $extraCost->creator?->name ?? '-' }}</td>
                    <td>{{ optional($extraCost->created_at)->format('d/m/Y H:i') }}</td>
                    <td><strong>R$ {{ number_format($extraCost->amount ?? 0, 2, ',', '.') }}</strong></td>
                </tr>
            @empty
                <tr>
                    <td colspan="4">Nenhum custo avulso lançado.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="section-title">Linha do tempo</div>

    <div class="timeline-item">
        <strong>Abertura da manutenção</strong>
        <span>{{ optional($maintenance->started_at)->format('d/m/Y H:i') }}</span>
        <div>Status inicial: {{ $serviceStatuses[$maintenance->service_status] ?? '-' }}</div>
    </div>

    @foreach($maintenance->statusLogs->sortBy('created_at') as $log)
        @if($log->old_status)
            <div class="timeline-item">
                <strong>Status atualizado</strong>
                <span>{{ optional($log->created_at)->format('d/m/Y H:i') }}</span>
                <div>
                    {{ $serviceStatuses[$log->old_status] ?? $log->old_status }}
                    →
                    {{ $serviceStatuses[$log->new_status] ?? $log->new_status }}
                </div>

                @if($log->reason)
                    <div class="muted">{{ $log->reason }}</div>
                @endif
            </div>
        @endif
    @endforeach

    @foreach($maintenance->items->sortBy('created_at') as $item)
        <div class="timeline-item">
            <strong>Procedimento realizado</strong>
            <span>{{ optional($item->created_at)->format('d/m/Y H:i') }}</span>
            <div>
                {{ $item->procedure->name ?? '-' }}
                —
                R$ {{ number_format($item->total_cost ?? 0, 2, ',', '.') }}
            </div>
        </div>
    @endforeach

    @foreach($maintenance->extraCosts->sortBy('created_at') as $extraCost)
        <div class="timeline-item">
            <strong>Custo avulso lançado</strong>
            <span>{{ optional($extraCost->created_at)->format('d/m/Y H:i') }}</span>
            <div>
                {{ $extraCost->description }}
                —
                R$ {{ number_format($extraCost->amount ?? 0, 2, ',', '.') }}
            </div>
        </div>
    @endforeach

    @if($maintenance->workflow_status === 'closed')
        <div class="timeline-item">
            <strong>Encerramento da manutenção</strong>
            <span>{{ optional($maintenance->finished_at)->format('d/m/Y H:i') }}</span>
            <div>{{ $maintenance->closure_notes ?? 'Manutenção encerrada.' }}</div>
        </div>
    @endif

    @if($maintenance->workflow_status === 'cancelled')
        <div class="timeline-item">
            <strong>Cancelamento da manutenção</strong>
            <span>{{ optional($maintenance->cancelled_at)->format('d/m/Y H:i') }}</span>
            <div>{{ $maintenance->cancel_reason ?? 'Manutenção cancelada.' }}</div>
        </div>
    @endif

    <div class="total-box">
        <span>Total da ordem</span>
        <strong>R$ {{ number_format($maintenance->total_cost ?? 0, 2, ',', '.') }}</strong>
    </div>

    <div class="footer">
        Relatório gerado automaticamente pelo CHM —
        {{ now()->format('d/m/Y H:i') }}
    </div>
</body>
</html>