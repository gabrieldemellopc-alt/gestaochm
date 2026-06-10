@extends('layouts.app')

@php
    $pageTitle = 'Oficina';
    $pageSubtitle = 'Pneus';
@endphp

@push('styles')
<link
    rel="stylesheet"
    href="{{ asset('css/pages/workshop-tires.css') }}?v=4"
>
@endpush

@section('content')

<div class="workshop-tires-page">


    <div class="workshop-hero">

        <div>
            <span>
                Oficina
            </span>

            <h1>
                Controle de pneus
            </h1>

            <p>
                Cadastre entradas, acompanhe estoque, pneus instalados e status operacional.
            </p>
        </div>

        <a
            href="{{ route('dashboard') }}"
            class="workshop-hero-btn"
        >
            <i data-lucide="arrow-left"></i>
            Voltar ao dashboard
        </a>

    </div>

    <div class="workshop-summary-grid">

        <div class="workshop-summary-card">
            <small>Total</small>
            <strong>{{ $summary['total'] }}</strong>
        </div>

        <div class="workshop-summary-card available">
            <small>Disponíveis</small>
            <strong>{{ $summary['available'] }}</strong>
        </div>

        <div class="workshop-summary-card installed">
            <small>Instalados</small>
            <strong>{{ $summary['installed'] }}</strong>
        </div>

        <div class="workshop-summary-card maintenance">
            <small>Manutenção</small>
            <strong>{{ $summary['maintenance'] }}</strong>
        </div>

        <div class="workshop-summary-card discarded">
            <small>Descartados</small>
            <strong>{{ $summary['discarded'] }}</strong>
        </div>

    </div>

    <div class="workshop-grid">

        <section class="workshop-card">

            <div class="workshop-card-header">

                <div>
                    <h2>
                        Entrada de pneus
                    </h2>

                    <p>
                        Registre uma compra/lote e gere pneus individuais no estoque.
                    </p>
                </div>

                <i data-lucide="package-plus"></i>

            </div>

            <form
                method="POST"
                action="{{ route('workshop.tires.entries.store') }}"
                class="workshop-entry-form"
            >

                @csrf

                <div class="workshop-form-grid">

                    <div class="form-group">
                        <label>Data da entrada</label>
                        <input
                            type="date"
                            name="entry_date"
                            value="{{ old('entry_date', now()->format('Y-m-d')) }}"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label>Quantidade</label>
                        <input
                            type="number"
                            name="quantity"
                            value="{{ old('quantity', 1) }}"
                            min="1"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label>Prefixo do código</label>
                        <input
                            type="text"
                            name="code_prefix"
                            value="{{ old('code_prefix', 'PN') }}"
                            placeholder="Ex: PN, AKSA-PN"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label>Marca</label>
                        <input
                            type="text"
                            name="brand"
                            value="{{ old('brand') }}"
                            placeholder="Ex: Michelin"
                        >
                    </div>

                    <div class="form-group">
                        <label>Modelo/Banda</label>
                        <input
                            type="text"
                            name="model"
                            value="{{ old('model') }}"
                            placeholder="Ex: X Multi"
                        >
                    </div>

                    <div class="form-group">
                        <label>Medida</label>
                        <input
                            type="text"
                            name="size"
                            value="{{ old('size') }}"
                            placeholder="Ex: 275/80 R22.5"
                        >
                    </div>

                    <div class="form-group">
                        <label>
                            Sulco inicial
                        </label>
                    
                        <div class="input-with-suffix">
                            <input
                                type="number"
                                step="0.01"
                                name="initial_tread_depth"
                                value="{{ old('initial_tread_depth') }}"
                                placeholder="Ex: 15"
                            >
                    
                            <span>
                                mm
                            </span>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>
                            Alerta de atenção
                        </label>
                    
                        <div class="input-with-suffix">
                            <input
                                type="number"
                                step="0.01"
                                name="warning_tread_depth"
                                value="{{ old('warning_tread_depth', 5) }}"
                                placeholder="Ex: 5.00"
                            >
                    
                            <span>
                                mm
                            </span>
                        </div>
                    
                        <small class="form-help">
                            Valor sugerido: 5 mm. Abaixo ou igual a este sulco, o pneu entra em atenção.
                        </small>
                    </div>
                    
                    <div class="form-group">
                        <label>
                            Alerta crítico
                        </label>
                    
                        <div class="input-with-suffix">
                            <input
                                type="number"
                                step="0.01"
                                name="critical_tread_depth"
                                value="{{ old('critical_tread_depth', 3) }}"
                                placeholder="Ex: 3.00"
                            >
                    
                            <span>
                                mm
                            </span>
                        </div>
                    
                        <small class="form-help">
                            Valor sugerido: 3 mm. Abaixo ou igual a este sulco, o pneu fica crítico.
                        </small>
                    </div>

                    <div class="form-group">
                        <label>Valor unitário</label>
                        <input
                            type="number"
                            step="0.01"
                            name="unit_cost"
                            value="{{ old('unit_cost') }}"
                            placeholder="Ex: 1200.00"
                        >
                    </div>

                    <div class="form-group">
                        <label>Fornecedor</label>
                        <input
                            type="text"
                            name="supplier_name"
                            value="{{ old('supplier_name') }}"
                            placeholder="Ex: Pneus Bahia"
                        >
                    </div>

                    <div class="form-group">
                        <label>Nota fiscal</label>
                        <input
                            type="text"
                            name="invoice_number"
                            value="{{ old('invoice_number') }}"
                            placeholder="Ex: NF 12345"
                        >
                    </div>

                </div>

                <div class="form-group">
                    <label>Observações</label>
                    <textarea
                        name="notes"
                        rows="3"
                        placeholder="Informações adicionais sobre a entrada..."
                    >{{ old('notes') }}</textarea>
                </div>

                <button
                    type="submit"
                    class="workshop-submit-btn"
                >
                    <i data-lucide="save"></i>
                    Registrar entrada
                </button>

            </form>

        </section>

        <aside class="workshop-card">

            <div class="workshop-card-header">

                <div>
                    <h2>
                        Últimas entradas
                    </h2>

                    <p>
                        Lotes registrados recentemente.
                    </p>
                </div>

                <i data-lucide="history"></i>

            </div>

            <div class="workshop-entry-list">

                @forelse($entries as $entry)

                    <div class="workshop-entry-item">

                        <div>
                            <strong>
                                {{ optional($entry->entry_date)->format('d/m/Y') }}
                            </strong>

                            <span>
                                {{ $entry->items_count }} pneu(s)
                                ·
                                {{ $entry->brand ?? 'Sem marca' }}
                            </span>
                        </div>

                        <small>
                            {{ $entry->invoice_number ?? 'Sem NF' }}
                        </small>

                    </div>

                @empty

                    <div class="workshop-empty">
                        Nenhuma entrada registrada.
                    </div>

                @endforelse

            </div>

        </aside>

    </div>

    <section class="workshop-card">

        <div class="workshop-card-header">

            <div>
                <h2>
                    Pneus cadastrados
                </h2>

                <p>
                    Estoque, pneus instalados e situação atual.
                </p>
            </div>

            <i data-lucide="circle-dot"></i>

        </div>

        <form
                method="GET"
                action="{{ route('workshop.tires.index') }}"
                class="workshop-tire-filters"
            >
            
                <div class="workshop-tire-search">
                    <i data-lucide="search"></i>
            
                    <input
                        type="text"
                        name="search"
                        value="{{ request('search') }}"
                        placeholder="Buscar por código, marca, modelo ou medida..."
                    >
                </div>
            
                <div class="workshop-tire-status-filters">
            
                    <a
                        href="{{ route('workshop.tires.index') }}"
                        class="{{ ! request('status') ? 'active' : '' }}"
                    >
                        Todos
                    </a>
            
                    <a
                        href="{{ route('workshop.tires.index', ['status' => 'available', 'search' => request('search')]) }}"
                        class="{{ request('status') === 'available' ? 'active' : '' }}"
                    >
                        Disponíveis
                    </a>
            
                    <a
                        href="{{ route('workshop.tires.index', ['status' => 'installed', 'search' => request('search')]) }}"
                        class="{{ request('status') === 'installed' ? 'active' : '' }}"
                    >
                        Instalados
                    </a>
            
                    <a
                        href="{{ route('workshop.tires.index', ['status' => 'maintenance', 'search' => request('search')]) }}"
                        class="{{ request('status') === 'maintenance' ? 'active' : '' }}"
                    >
                        Manutenção
                    </a>
            
                    <a
                        href="{{ route('workshop.tires.index', ['status' => 'discarded', 'search' => request('search')]) }}"
                        class="{{ request('status') === 'discarded' ? 'active' : '' }}"
                    >
                        Descartados
                    </a>
            
                </div>
            
                <button
                    type="submit"
                    class="workshop-filter-submit"
                >
                    Filtrar
                </button>
            
            </form>
            
<div class="workshop-table">

    <div class="workshop-table-head">

        <div>Pneu</div>
        <div>Marca/Modelo</div>
        <div>Status</div>
        <div>Localização</div>
        <div>Sulco atual</div>
        <div>Ações</div>

    </div>

    <div class="workshop-table-body">

        @forelse($tires as $tire)

            @php
                $installation = $tire->activeInstallation;
            @endphp

            <div class="workshop-table-row">

                <div>
                    <strong>
                        {{ $tire->code }}
                    </strong>

                    <span>
                        {{ $tire->size ?? 'Sem medida' }}
                    </span>
                </div>

                <div>
                    {{ $tire->brand ?? 'Sem marca' }}
                    {{ $tire->model ? '· ' . $tire->model : '' }}
                </div>

                <div class="workshop-status-head">
                    <span class="workshop-status {{ $tire->status }}">
                        @switch($tire->status)
                            @case('available')
                                Disponível{{ $tire->retreads_count > 0 ? '-R' . $tire->retreads_count : '' }}
                                @break

                            @case('installed')
                                Instalado{{ $tire->retreads_count > 0 ? '-R' . $tire->retreads_count : '' }}
                                @break

                            @case('maintenance')
                                Manutenção{{ $tire->retreads_count > 0 ? '-R' . $tire->retreads_count : '' }}
                                @break

                            @case('discarded')
                                Descartado{{ $tire->retreads_count > 0 ? '-R' . $tire->retreads_count : '' }}
                                @break

                            @default
                                {{ $tire->status }}
                        @endswitch
                    </span>
                </div>

                <div>
                    @if($installation)
                        {{ $installation->vehicle?->plate }}
                        ·
                        {{ $installation->position_code }}
                    @else
                        Estoque
                    @endif
                </div>

                <div>
                    @if($tire->current_tread_depth !== null)
                        {{ number_format($tire->current_tread_depth, 2, ',', '.') }} mm
                    @else
                        --
                    @endif
                </div>

                <div>
                    @php
                        $tirePayload = [
                            'id' => $tire->id,
                            'code' => $tire->code,
                            'brand' => $tire->brand,
                            'model' => $tire->model,
                            'size' => $tire->size,
                            'initial_tread_depth' => $tire->initial_tread_depth,
                            'warning_tread_depth' => $tire->warning_tread_depth,
                            'critical_tread_depth' => $tire->critical_tread_depth,
                            'status' => $tire->status,
                            'notes' => $tire->notes,
                        ];
                    @endphp
                    
                    @if($tire->status === 'maintenance')

                        <button

                            type="button"

                            class="workshop-table-action retread"

                            onclick="openRetreadModal({{ $tire->id }}, {{ Illuminate\Support\Js::from($tire->code) }})"

                        >

                            <i data-lucide="refresh-cw"></i>

                            Recapar

                        </button>

                    @endif


                    <a
                        href="{{ route('workshop.tires.history', $tire) }}"
                        class="workshop-table-action"
                    >
                        <i data-lucide="history"></i>
                        Histórico
                    </a>


                    <button
                        type="button"
                        class="workshop-table-action"
                        onclick="openEditTireModal({{ Illuminate\Support\Js::from($tirePayload) }})"
                    >
                        <i data-lucide="edit-3"></i>
                        Editar
                    </button>
                </div>

            </div>

        @empty

            <div class="workshop-empty">
                Nenhum pneu cadastrado.
            </div>

        @endforelse

    </div>

</div>        
        @if($tires->hasPages())
        
            <div class="workshop-pagination">
        
                <div class="workshop-pagination-info">
                    Mostrando
                    <strong>{{ $tires->firstItem() }}</strong>
                    a
                    <strong>{{ $tires->lastItem() }}</strong>
                    de
                    <strong>{{ $tires->total() }}</strong>
                    pneus
                </div>
        
                <div class="workshop-pagination-links">
        
                    @if($tires->onFirstPage())
                        <span class="disabled">
                            Anterior
                        </span>
                    @else
                        <a href="{{ $tires->previousPageUrl() }}">
                            Anterior
                        </a>
                    @endif
        
                    @foreach($tires->getUrlRange(1, $tires->lastPage()) as $page => $url)
        
                        @if($page == $tires->currentPage())
                            <span class="active">
                                {{ $page }}
                            </span>
                        @else
                            <a href="{{ $url }}">
                                {{ $page }}
                            </a>
                        @endif
        
                    @endforeach
        
                    @if($tires->hasMorePages())
                        <a href="{{ $tires->nextPageUrl() }}">
                            Próxima
                        </a>
                    @else
                        <span class="disabled">
                            Próxima
                        </span>
                    @endif
        
                </div>
        
            </div>
        
        @endif

    </section>

</div>
<div
    class="workshop-modal-overlay"
    id="editTireModal"
    style="display:none;"
>
    <div class="workshop-tire-modal">

        <div class="workshop-tire-modal-header">

            <div>
                <span>
                    Oficina / Pneus
                </span>

                <h2>
                    Editar pneu
                </h2>

                <p id="editTireCode">
                    —
                </p>
            </div>

            <button
                type="button"
                onclick="closeEditTireModal()"
            >
                <i data-lucide="x"></i>
            </button>

        </div>

        <form
            method="POST"
            id="editTireForm"
            class="workshop-tire-modal-form"
        >
            @csrf
            @method('PUT')

            <div class="workshop-form-grid">

                <div class="form-group">
                    <label>Marca</label>
                    <input type="text" name="brand" id="editTireBrand">
                </div>

                <div class="form-group">
                    <label>Modelo/Banda</label>
                    <input type="text" name="model" id="editTireModel">
                </div>

                <div class="form-group">
                    <label>Medida</label>
                    <input type="text" name="size" id="editTireSize">
                </div>

                <div class="form-group">
                    <label>Status</label>

                    <select name="status" id="editTireStatus">
                        <option value="available">Disponível</option>
                        <option value="maintenance">Manutenção/Reforma</option>
                        <option value="discarded">Descartado</option>
                        <option value="installed">Instalado</option>
                    </select>

                    <small class="form-help">
                        Pneus instalados devem ser removidos pela tela do veículo.
                    </small>
                </div>

                <div class="form-group">
                    <label>Sulco atual / inicial</label>

                    <div class="input-with-suffix">
                        <input
                            type="number"
                            step="0.01"
                            name="initial_tread_depth"
                            id="editTireInitialTread"
                        >

                        <span>mm</span>
                    </div>

                    <small class="form-help">
                        Em caso de reforma, informe o novo sulco após retorno.
                    </small>
                </div>

                <div class="form-group">
                    <label>Alerta de atenção</label>

                    <div class="input-with-suffix">
                        <input
                            type="number"
                            step="0.01"
                            name="warning_tread_depth"
                            id="editTireWarningTread"
                        >

                        <span>mm</span>
                    </div>
                </div>

                <div class="form-group">
                    <label>Alerta crítico</label>

                    <div class="input-with-suffix">
                        <input
                            type="number"
                            step="0.01"
                            name="critical_tread_depth"
                            id="editTireCriticalTread"
                        >

                        <span>mm</span>
                    </div>
                </div>

            </div>

            <div class="form-group">
                <label>Observações</label>

                <textarea
                    name="notes"
                    id="editTireNotes"
                    rows="3"
                    placeholder="Ex: pneu retornou de reforma, nova banda aplicada..."
                ></textarea>
            </div>

            <div class="workshop-tire-modal-footer">

                <button
                    type="button"
                    class="workshop-modal-cancel"
                    onclick="closeEditTireModal()"
                >
                    Cancelar
                </button>

                <button
                    type="submit"
                    class="workshop-modal-save"
                >
                    <i data-lucide="save"></i>
                    Salvar alterações
                </button>

            </div>

        </form>

    </div>
</div>
<div class="workshop-modal-overlay" id="retreadTireModal" style="display:none;">

    <div class="workshop-tire-modal workshop-retread-modal">

        <div class="workshop-tire-modal-header">

            <div>

                <span>Oficina / Pneus</span>

                <h2>Registrar recapagem</h2>

                <p id="retreadTireCode">Pneu</p>

            </div>

            <button type="button" onclick="closeRetreadModal()">

                <i data-lucide="x"></i>

            </button>

        </div>

        <form method="POST" id="retreadTireForm" class="workshop-tire-modal-form">

            @csrf

            <div class="workshop-retread-form-grid">

                <div class="form-group">

                    <label>Novo sulco</label>

                    <div class="input-with-suffix">

                        <input type="number" name="new_tread_depth" step="0.01" min="0.01" max="50" required>

                        <span>mm</span>

                    </div>

                </div>

                <div class="form-group">

                    <label>Data da recapagem</label>

                    <input type="date" name="retreaded_at" value="{{ now()->format('Y-m-d') }}" required>

                </div>

                <div class="form-group full-width">

                    <label>Fornecedor / quem recapou</label>

                    <input type="text" name="provider_name" maxlength="150" required>

                </div>

                <div class="form-group full-width">

                    <label>Observação</label>

                    <textarea name="notes" rows="3" maxlength="2000"></textarea>

                </div>

            </div>

            <div class="workshop-tire-modal-footer">

                <button type="button" class="workshop-modal-cancel" onclick="closeRetreadModal()">

                    Cancelar

                </button>

                <button type="submit" class="workshop-modal-save retread">

                    <i data-lucide="refresh-cw"></i>

                    Registrar recapagem

                </button>

            </div>

        </form>

    </div>

</div>



<script>
function openRetreadModal(tireId, tireCode) {

    document.getElementById('retreadTireForm').action =

        "{{ url('/workshop/tires') }}/" + tireId + "/retreads";



    document.getElementById('retreadTireCode').innerText =

        tireCode || 'Pneu';



    document.getElementById('retreadTireModal').style.display =

        'flex';



    if (window.lucide) {

        lucide.createIcons();

    }

}



function closeRetreadModal() {

    document.getElementById('retreadTireModal').style.display =

        'none';

}



function openEditTireModal(tire) {
    const modal =
        document.getElementById('editTireModal');

    const form =
        document.getElementById('editTireForm');

    form.action =
        "{{ url('/workshop/tires') }}/" + tire.id;

    document.getElementById('editTireCode').innerText =
        tire.code || '—';

    document.getElementById('editTireBrand').value =
        tire.brand || '';

    document.getElementById('editTireModel').value =
        tire.model || '';

    document.getElementById('editTireSize').value =
        tire.size || '';

    document.getElementById('editTireInitialTread').value =
        tire.initial_tread_depth || '';

    document.getElementById('editTireWarningTread').value =
        tire.warning_tread_depth || 5;

    document.getElementById('editTireCriticalTread').value =
        tire.critical_tread_depth || 3;

    document.getElementById('editTireStatus').value =
        tire.status || 'available';

    document.getElementById('editTireNotes').value =
        tire.notes || '';

    modal.style.display =
        'flex';

    if (window.lucide) {
        lucide.createIcons();
    }
}

function closeEditTireModal() {
    document.getElementById('editTireModal').style.display =
        'none';
}
</script>
@endsection
