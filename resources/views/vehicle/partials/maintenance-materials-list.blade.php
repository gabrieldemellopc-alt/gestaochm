@forelse($maintenance->materialUsages as $usage)
    <article class="maintenance-materials-list-item">
        <div class="maintenance-materials-list-copy">
            <strong>{{ $usage->stockItem?->name ?? 'Item de estoque' }}</strong>
            <span>{{ $usage->stockItem?->category?->name ?? 'Sem categoria' }} · {{ number_format($usage->quantity, 2, ',', '.') }} {{ $usage->stockItem?->unit }}</span>
            <small>{{ optional($usage->created_at)->format('d/m/Y H:i') }} · {{ $usage->creator?->name ?? 'Responsável não informado' }}</small>
            @if($usage->notes)<p>{{ $usage->notes }}</p>@endif
        </div>
        <div class="maintenance-materials-list-value">
            @if($canViewCosts)R$ {{ number_format($usage->total_cost, 2, ',', '.') }}@else Valor restrito @endif
        </div>

        @if($canCancelMaterials)
            <details class="maintenance-materials-actions">
                <summary>Corrigir ou cancelar</summary>
                <div class="maintenance-materials-action-grid">
                    <form method="POST" action="{{ route('vehicles.maintenance.materials.replace', [$vehicle->id, $maintenance->id, $usage->id]) }}" @submit.prevent="submitAction($event)">
                        @csrf
                        <strong>Corrigir quantidade</strong>
                        <input type="hidden" name="stock_item_id" value="{{ $usage->stock_item_id }}">
                        <label>Nova quantidade<input type="number" name="quantity" min="0.01" step="0.01" value="{{ $usage->quantity }}" required></label>
                        <label>Observação<input type="text" name="notes" value="{{ $usage->notes }}" maxlength="2000"></label>
                        <label>Motivo<textarea name="change_reason" minlength="10" required></textarea></label>
                        <button type="submit" class="maintenance-materials-secondary">Corrigir</button>
                    </form>
                    <form method="POST" action="{{ route('vehicles.maintenance.materials.cancel', [$vehicle->id, $maintenance->id, $usage->id]) }}" @submit.prevent="submitAction($event)">
                        @csrf
                        <strong>Cancelar consumo</strong>
                        <label>Motivo<textarea name="reason" minlength="10" required></textarea></label>
                        <button type="submit" class="maintenance-materials-danger">Cancelar e devolver</button>
                    </form>
                </div>
            </details>
        @endif
    </article>
@empty
    <div class="maintenance-materials-empty materials-empty-state">
        <strong>Nenhum material lançado ainda.</strong>
        <span>Os materiais adicionados nesta ordem aparecerão aqui.</span>
    </div>
@endforelse
