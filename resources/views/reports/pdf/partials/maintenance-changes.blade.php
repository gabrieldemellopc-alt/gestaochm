@if((($canViewChanges ?? false) || ($canViewCancelled ?? false)) && collect($maintenanceChangeRows ?? [])->contains(fn ($maintenance) => ! empty($maintenance['changes'] ?? [])))
    <section style="page-break-before: auto; margin-top: 26px; font-family: DejaVu Sans; color: #111827; font-size: 10px;">
        <div style="page-break-inside: avoid; margin-bottom: 12px;">
            <h3 style="margin: 0 0 5px; font-size: 15px; line-height: 1.3; color: #0f172a;">Alterações e cancelamentos</h3>

            <p style="margin: 0; color: #64748b; line-height: 1.4;">
                Os lançamentos anteriores são exibidos apenas para rastreabilidade e não compõem totais, rankings ou médias.
            </p>
        </div>

        @foreach($maintenanceChangeRows as $maintenance)
            @foreach($maintenance['changes'] ?? [] as $change)
                <div style="page-break-inside: avoid; margin: 0 0 12px; border: 1px solid #dbe2ea;">
                <table style="width: 100%; border-collapse: collapse; margin: 0;">
                    <thead>
                        <tr>
                            <th colspan="4" style="background: #f1f5f9; color: #0f172a; padding: 7px 9px; text-align: left; border-bottom: 1px solid #dbe2ea;">
                                <span style="display: inline-block; background: #fef3c7; color: #92400e; padding: 2px 6px; margin-right: 7px; font-size: 9px; font-weight: bold;">
                                    {{ match ($change['type'] ?? null) { 'material_replacement' => 'Material corrigido', 'material_cancelled' => 'Material cancelado', 'replacement' => 'Serviço substituído', default => 'Corrigido' } }}
                                </span>
                                Ordem #{{ $maintenance['id'] }} - {{ $maintenance['vehicle_name'] }} ({{ $maintenance['vehicle_plate'] }})
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td style="width: 25%; padding: 7px 9px; border-bottom: 1px solid #e5e7eb;"><strong>{{ str_starts_with($change['type'] ?? '', 'material_') ? 'Material anterior' : 'Serviço anterior' }}</strong><br>{{ $change['old_procedure'] ?: 'Não informado' }}@if(isset($change['old_quantity'])) ({{ $change['old_quantity'] }} {{ $change['old_unit'] ?? '' }})@endif</td>
                            <td style="width: 25%; padding: 7px 9px; border-bottom: 1px solid #e5e7eb;"><strong>{{ str_starts_with($change['type'] ?? '', 'material_') ? 'Novo material' : 'Novo serviço' }}</strong><br>{{ $change['replacement_procedure'] ?: 'Cancelado' }}@if(isset($change['replacement_quantity'])) ({{ $change['replacement_quantity'] }} {{ $change['old_unit'] ?? '' }})@endif</td>
                            <td style="width: 25%; padding: 7px 9px; border-bottom: 1px solid #e5e7eb;"><strong>Responsável</strong><br>{{ $change['changed_by'] ?: 'Não informado' }}</td>
                            <td style="width: 25%; padding: 7px 9px; border-bottom: 1px solid #e5e7eb;"><strong>Data/hora</strong><br>{{ $change['changed_at'] ? optional($change['changed_at'])->format('d/m/Y H:i') : 'Não informado' }}</td>
                        </tr>
                        <tr>
                            <td colspan="2" style="padding: 9px; border-bottom: 1px solid #e5e7eb;"><strong>Motivo</strong><br>{{ $change['reason'] ?: '-' }}</td>
                            <td style="padding: 9px; border-bottom: 1px solid #e5e7eb;">
                                <strong>Consumo devolvido</strong><br>
                                @forelse($change['returned_stock'] as $movement)
                                    {{ $movement['item'] }} ({{ $movement['quantity'] }})<br>
                                @empty
                                    -
                                @endforelse
                            </td>
                            <td style="padding: 9px; border-bottom: 1px solid #e5e7eb;">
                                <strong>Novo consumo</strong><br>
                                @forelse($change['new_stock'] as $movement)
                                    {{ $movement['item'] }} ({{ $movement['quantity'] }})<br>
                                @empty
                                    -
                                @endforelse
                            </td>
                        </tr>
                        @if($canViewCosts ?? false)
                            <tr>
                                <td colspan="2" style="padding: 9px;"><strong>Custo anterior:</strong> R$ {{ number_format($change['old_cost'] ?? 0, 2, ',', '.') }}</td>
                                <td colspan="2" style="padding: 9px;"><strong>Novo custo:</strong> R$ {{ number_format($change['replacement_cost'] ?? 0, 2, ',', '.') }}</td>
                            </tr>
                        @endif
                    </tbody>
                </table>
                </div>
            @endforeach
        @endforeach
    </section>
@endif
