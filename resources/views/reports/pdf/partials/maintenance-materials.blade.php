@if(collect($maintenanceChangeRows ?? [])->contains(fn ($maintenance) => ! empty($maintenance['materials'] ?? [])))
    <section style="page-break-before: auto; margin-top: 26px; font-family: DejaVu Sans; color: #111827; font-size: 10px;">
        <h3 style="margin: 0 0 5px; font-size: 15px; color: #0f172a;">Materiais utilizados</h3>
        <p style="margin: 0 0 12px; color: #64748b;">Consumos diretos de estoque, separados dos materiais vinculados aos procedimentos. Os valores da ordem já incluem estes materiais e não são somados novamente.</p>
        <table style="width:100%; border-collapse:collapse; margin:0;">
            <thead><tr><th>Ordem</th><th>Veículo</th><th>Item / categoria</th><th>Quantidade</th><th>Responsável / data</th><th>Observação</th>@if($canViewCosts ?? false)<th>Custo</th>@endif</tr></thead>
            <tbody>
                @foreach($maintenanceChangeRows as $maintenance)
                    @foreach($maintenance['materials'] ?? [] as $material)
                        <tr>
                            <td>#{{ $maintenance['id'] }}</td>
                            <td>{{ $maintenance['vehicle_name'] }}<br>{{ $maintenance['vehicle_plate'] }}</td>
                            <td>{{ $material['name'] }}<br>{{ $material['category'] ?: 'Sem categoria' }}</td>
                            <td>{{ number_format($material['quantity'], 2, ',', '.') }} {{ $material['unit'] }}</td>
                            <td>{{ $material['created_by_name'] }}<br>{{ optional($material['created_at'])->format('d/m/Y H:i') }}</td>
                            <td>{{ $material['notes'] ?: '-' }}</td>
                            @if($canViewCosts ?? false)<td>R$ {{ number_format($material['total_cost'] ?? 0, 2, ',', '.') }}</td>@endif
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>
    </section>
@endif
