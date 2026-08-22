@if(collect($maintenances ?? [])->contains(fn ($maintenance) => $maintenance->materialUsages->isNotEmpty()))
    <section style="page-break-before: auto; margin-top: 26px; font-family: DejaVu Sans; color: #111827; font-size: 10px;">
        <h3 style="margin: 0 0 5px; font-size: 15px; color: #0f172a;">MATERIAIS DAS MANUTENÇÕES</h3>
        <p style="margin: 0 0 12px; color: #64748b;">Materiais vinculados às ordens de manutenção.</p>
        <table style="width:100%; border-collapse:collapse; margin:0;">
            <thead><tr><th>Ordem</th><th>Veículo</th><th>Item / categoria</th><th>Quantidade</th><th>Responsável / data</th><th>Observação</th>@if($canViewCosts ?? false)<th>Custo</th>@endif</tr></thead>
            <tbody>
                @foreach($maintenances as $maintenance)
                    @foreach($maintenance->materialUsages as $usage)
                        @php
                            $effectiveDate = $usage->created_at;
                        @endphp
                        <tr>
                            <td>#{{ $maintenance->id }}</td>
                            <td>{{ $maintenance->vehicle?->name ?? '-' }}<br>{{ $maintenance->vehicle?->plate ?? '-' }}</td>
                            <td>
                                {{ $usage->stockItem?->name ?? '-' }}<br>
                                {{ $usage->stockItem?->category?->name ?: 'Sem categoria' }}
                            </td>
                            <td>
                                @if($usage->quantity === null)
                                    Quantidade: Não informada
                                @else
                                    {{ number_format($usage->quantity, 2, ',', '.') }} {{ $usage->stockItem?->unit }}
                                @endif
                            </td>
                            <td>{{ $usage->creator?->name ?? 'Não informado' }}<br>{{ $effectiveDate ? $effectiveDate->format('d/m/Y H:i') : 'Não informado' }}</td>
                            <td>{{ $usage->notes ?: '-' }}</td>
                            @if($canViewCosts ?? false)<td>R$ {{ number_format($usage->total_cost, 2, ',', '.') }}</td>@endif
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>
    </section>
@endif
