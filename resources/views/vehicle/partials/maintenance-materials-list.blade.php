@foreach($maintenance->materialUsages as $usage)
    <article class="maintenance-materials-list-item maintenance-material-entry maintenance-material-entry--direct">
        <div class="maintenance-materials-list-copy">
            <strong>{{ $usage->stockItem?->name ?? 'Item de estoque' }}</strong>
            <span>{{ $usage->stockItem?->category?->name ?? 'Sem categoria' }} · {{ number_format($usage->quantity, 2, ',', '.') }} {{ $usage->stockItem?->unit }}</span>
            <small>{{ optional($usage->created_at)->format('d/m/Y H:i') }} · {{ $usage->creator?->name ?? 'Responsável não informado' }}</small>
            @if($usage->notes)<p>{{ $usage->notes }}</p>@endif
        </div>
        <div class="maintenance-materials-list-value">
            @if($canViewCosts)R$ {{ number_format($usage->total_cost, 2, ',', '.') }}@else Valor restrito @endif
        </div>

        @if($canCancelMaterials && ! ($readonlySummary ?? false))
            <details class="maintenance-materials-actions">
                <summary>Corrigir ou cancelar</summary>
                <div class="maintenance-materials-action-grid">
                    <form method="POST" action="{{ route('vehicles.maintenance.materials.replace', [$vehicle->id, $maintenance->id, $usage->id]) }}" @submit.prevent="submitAction($event)">
                        @csrf
                        <strong>Corrigir quantidade</strong>
                        <input type="hidden" name="stock_item_id" value="{{ $usage->stock_item_id }}">
                        <label>Nova quantidade<input type="number" name="quantity" min="1" step="1" value="{{ (int) $usage->quantity }}" required></label>
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
@endforeach

@foreach($maintenance->procedureMaterialMovements as $movement)
    @php($procedureName = $movement->maintenanceRecordItem?->procedure?->name ?? 'Procedimento não informado')
    <article class="maintenance-materials-list-item maintenance-material-entry maintenance-material-entry--procedure">
        <div class="maintenance-materials-list-copy">
            <div class="maintenance-material-origin-row">
                <strong>{{ $movement->stockItem?->name ?? 'Item de estoque' }}</strong>
                <span class="maintenance-material-origin-badge">Procedimento</span>
            </div>
            <span>{{ $movement->stockItem?->category?->name ?? 'Sem categoria' }} · {{ number_format($movement->quantity, 2, ',', '.') }} {{ $movement->stockItem?->unit }}</span>
            <small>Procedimento: {{ $procedureName }}</small>
            <p class="maintenance-material-origin-note">* Item vinculado ao procedimento {{ $procedureName }}. Para corrigir, acesse o serviço/procedimento correspondente.</p>
        </div>
        <div class="maintenance-materials-list-value">
            @if($canViewCosts)R$ {{ number_format($movement->total_cost, 2, ',', '.') }}@else Valor restrito @endif
        </div>
    </article>
@endforeach

@if($maintenance->materialUsages->isEmpty() && $maintenance->procedureMaterialMovements->isEmpty())
    <div class="maintenance-materials-empty materials-empty-state">
        <strong>Nenhum material utilizado registrado ainda.</strong>
        <span>Os materiais adicionados nesta manutenção aparecerão aqui.</span>
    </div>
@endif
