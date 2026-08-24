<div class="edit-card vehicle-full-card" x-data>
    <div class="card-header vehicle-procedures-header">
        <div>
            <h3>Procedimentos aplicáveis</h3>
            <p class="card-description">Selecione quais procedimentos este veículo poderá executar.</p>
        </div>
        <div class="vehicle-procedures-actions" aria-label="Controles de procedimentos">
            <button type="button" @click="$el.closest('.edit-card').querySelectorAll('input[name=&quot;procedures[]&quot;]').forEach(input => input.checked = true)">Selecionar todos</button>
            <button type="button" @click="$el.closest('.edit-card').querySelectorAll('input[name=&quot;procedures[]&quot;]').forEach(input => input.checked = false)">Desmarcar todos</button>
        </div>
    </div>

    <div class="procedures-grid">
        @foreach($procedures as $procedure)
            <label class="procedure-pill">
                <input
                    type="checkbox"
                    name="procedures[]"
                    value="{{ $procedure->id }}"
                    @checked(in_array($procedure->id, old('procedures', $vehicle->procedures->pluck('id')->all())))
                >
                <span>{{ $procedure->name }}</span>
            </label>
        @endforeach
    </div>
</div>
