@extends('layouts.app')

@php
    $pageTitle = 'Controle de Pneus';
    $pageSubtitle = $vehicle->plate . ' · ' . $vehicle->name;
@endphp

@push('styles')
<link
    rel="stylesheet"
    href="{{ asset('css/pages/vehicle-tires.css') }}"
>
@endpush

@section('content')

<div
    class="tire-page"
    x-data="vehicleTiresPage()"
>

    <div class="tire-hero">

        <div>

            <span>
                Gestão de pneus
            </span>

            <h1>
                {{ $vehicle->plate }} · {{ $vehicle->name }}
            </h1>

            <p>
                Controle de posições, instalação e medições de sulco dos pneus do veículo.
            </p>

        </div>
        <div class="tire-hero-actions">

        <a
            href="{{ route('vehicles.tires.report', $vehicle) }}"
            target="_blank"
            class="tire-back-btn"
        >
            <i data-lucide="file-text"></i>
            Gerar relatório
        </a>
        <a
            href="{{ route('dashboard') }}"
            class="tire-back-btn"
        >
            <i data-lucide="arrow-left"></i>
            Voltar ao dashboard
        </a>

        </div>
    </div>

    <div class="tire-position-grid">

        @foreach($positions as $position)

            @php
                $installation = $position['installation'];
                $tire = $position['tire'];
                $measurement = $position['latest_measurement'];
                $status = $position['status'];
            @endphp

            <section class="tire-position-card {{ $status }}">

                <div class="tire-position-header">

                    <div>

                        <small>
                            {{ $position['code'] }}
                        </small>

                        <h2>
                            {{ $position['label'] }}
                        </h2>

                    </div>

                    <span class="tire-status {{ $status }}">
                        @switch($status)
                            @case('empty')
                                Sem pneu
                                @break

                            @case('pending')
                                Sem medição
                                @break

                            @case('danger')
                                Crítico
                                @break

                            @case('warning')
                                Atenção
                                @break

                            @default
                                OK
                        @endswitch
                    </span>

                </div>

                @if($tire)

                    <div class="tire-info-box">

                        <div>
                            <span>
                                Pneu
                            </span>

                            <strong>
                                {{ $tire->code }}

                                @if($tire->retreads_count > 0)
                                    <small>R{{ $tire->retreads_count }}</small>
                                @endif
                            </strong>
                        </div>

                        <div>
                            <span>
                                Marca / Modelo
                            </span>

                            <strong>
                                {{ $tire->brand ?? 'Sem marca' }}
                                {{ $tire->model ? '· ' . $tire->model : '' }}
                            </strong>
                        </div>

                        <div>
                            <span>
                                Sulco inicial
                            </span>

                            <strong>
                                {{ $tire->initial_tread_depth ?? '--' }} mm
                            </strong>
                        </div>
                        

                        <div>
                            <span>
                                KM instalação
                            </span>

                            <strong>
                                {{ $installation?->installed_km ? number_format($installation->installed_km, 0, ',', '.') . ' km' : '--' }}
                            </strong>
                        </div>

                    </div>

                    <div class="tire-measure-box">

                        <h3>
                            Referência atual
                        </h3>

                        @if($tire->current_tread_source !== 'initial')

                        <div class="tire-measure-grid">
                        
                            <div>
                                <span>
                                    Sulco atual
                                </span>
                        
                                <strong>
                                    {{ $tire->current_tread_depth }} mm
                                </strong>

                                @if($tire->current_tread_source === 'retread')
                                    <small>
                                        Referência: Recapagem R{{ $tire->retreads_count }}
                                    </small>
                                @elseif($tire->current_tread_source === 'measurement')
                                    <small>
                                        Referência: Última medição
                                    </small>
                                @endif
                            </div>
                        
                            <div>
                                <span>
                                    Desgaste
                                </span>
                        
                                <strong>
                                    {{ $position['wear_percent'] !== null ? $position['wear_percent'] . '%' : '--' }}
                                </strong>
                            </div>
                        
                            <div>
                                <span>
                                    KM
                                </span>
                        
                                <strong>
                                    {{ $measurement?->vehicle_km ? number_format($measurement->vehicle_km, 0, ',', '.') : '--' }}
                                </strong>
                            </div>
                        
                            <div>
                                <span>
                                    Data
                                </span>
                        
                                <strong>
                                    {{ optional($tire->current_tread_date)->format('d/m/Y') ?? '--' }}
                                </strong>
                            </div>
                        
                        </div>
                        @else

                            <p>
                                Nenhuma medição registrada para este pneu nesta posição.
                            </p>

                        @endif

                    </div>

                    <form
                        method="POST"
                        action="{{ route('vehicles.tires.measurement', $vehicle) }}"
                        class="tire-form"
                    >

                        @csrf

                        <input
                            type="hidden"
                            name="position_code"
                            value="{{ $position['code'] }}"
                        >

                        <input
                            type="hidden"
                            name="tire_id"
                            value="{{ $tire->id }}"
                        >

                        <div class="tire-form-title">
                            Nova medição de sulco
                        </div>

                    <div class="tire-form-grid simple-measurement">
                        <div>
                            <label>
                                KM atual
                            </label>
                        
                            <input
                                type="number"
                                name="vehicle_km"
                                value="{{ old('vehicle_km', $vehicle->current_km ?? 0) }}"
                                min="{{ $vehicle->current_km ?? 0 }}"
                                step="1"
                                required
                            >
                        </div>
                    
                         <div>
                            <label>
                                Sulco atual
                            </label>
                    
                            <input
                                type="number"
                                step="0.01"
                                name="current_tread"
                                value="{{ old('current_tread', $tire->current_tread_depth) }}"
                                placeholder="Ex: 16.68"
                                required
                            >
                        </div>
                        
                    
                        <div class="measurement-notes-field">
                            <label>
                                Observações
                            </label>
                    
                            <input
                                type="text"
                                name="notes"
                                placeholder="Opcional"
                            >
                        </div>
                    
                    </div>
                        <div class="tire-measure-actions">
                        
                            <button
                                type="button"
                                class="tire-remove-action"
                                @click='openRemoveTireModal(
                                    @json($position["code"]),
                                    @json($position["label"]),
                                    @json($tire->id),
                                    @json($tire->code),
                                    @json($vehicle->current_km ?? 0)
                                )'
                            >
                                <i data-lucide="refresh-cw"></i>
                                Remover / trocar pneu
                            </button>
                        
                            <button
                                type="submit"
                                class="tire-save-action"
                            >
                                <i data-lucide="save"></i>
                                Salvar medição
                            </button>
                        
                        </div>
                    </form>

                @else

                    <div class="tire-empty-box">

                        <i data-lucide="circle-off"></i>

                        <strong>
                            Nenhum pneu instalado
                        </strong>

                        <p>
                            Instale um pneu disponível nesta posição para iniciar o acompanhamento.
                        </p>

                    </div>

                    <div class="tire-install-box">
                    
                        <div class="tire-install-info">
                    
                            <span>
                                Posição disponível
                            </span>
                    
                            <strong>
                                {{ $position['code'] }} · {{ $position['label'] }}
                            </strong>
                    
                            <p>
                                Selecione um pneu disponível no estoque para instalar nesta posição.
                            </p>
                    
                        </div>
                    
                        <button
                            type="button"
                            class="tire-select-stock-btn"
                            @click='openTirePicker(
                                @json($position["code"]),
                                @json($position["label"]),
                                @json($vehicle->current_km ?? 0)
                            )'
                        >
                            <i data-lucide="search"></i>
                            Selecionar pneu do estoque
                        </button>
                    
                    </div>
                @endif

            </section>

        @endforeach

    </div>


{{-- MODAL: SELEÇÃO DE PNEU --}}
<div
    class="modal-overlay tire-picker-overlay"
    x-show="tirePickerOpen"
    x-transition.opacity
    style="display:none;"
    @click.self="closeTirePicker()"
>
    <div
        class="tire-picker-modal"
        x-transition.scale.origin.center
    >

        <div class="tire-picker-header">

            <div>
                <small>
                    Estoque de pneus
                </small>

                <h2>
                    Selecionar pneu
                </h2>

                <p>
                    Instalando em
                    <strong x-text="selectedPositionCode"></strong>
                    ·
                    <span x-text="selectedPositionLabel"></span>
                </p>
            </div>

            <button
                type="button"
                class="tire-picker-close"
                @click="closeTirePicker()"
            >
                <i data-lucide="x"></i>
            </button>

        </div>

        <div class="tire-picker-body">

            <div class="tire-picker-search">

                <i data-lucide="search"></i>

                <input
                    type="text"
                    x-model="tireSearch"
                    placeholder="Buscar por código, marca, modelo ou medida..."
                    autocomplete="off"
                >

            </div>

            <div class="tire-picker-count">

                <span>
                    Exibindo
                    <strong x-text="filteredTires().length"></strong>
                    pneu(s)
                </span>

                <small>
                    Digite para refinar a busca.
                </small>

            </div>

            <div class="tire-picker-list">

                <template
                    x-for="tire in filteredTires()"
                    :key="tire.id"
                >
                    <button
                        type="button"
                        class="tire-picker-item"
                        :class="{ selected: selectedTire && selectedTire.id === tire.id }"
                        @click="selectTire(tire)"
                    >

                        <div class="tire-picker-item-icon">
                            <i data-lucide="circle-dot"></i>
                        </div>

                        <div class="tire-picker-item-main">

                            <strong x-text="tire.code"></strong>

                            <span>
                                <template x-if="tire.brand">
                                    <span x-text="tire.brand"></span>
                                </template>

                                <template x-if="tire.model">
                                    <span>
                                        · <span x-text="tire.model"></span>
                                    </span>
                                </template>

                                <template x-if="tire.size">
                                    <span>
                                        · <span x-text="tire.size"></span>
                                    </span>
                                </template>
                            </span>

                        </div>

                        <div class="tire-picker-item-meta">

                            <small>
                                Sulco inicial
                            </small>

                            <b>
                                <span x-text="tire.initial_tread_depth ?? '--'"></span>
                                mm
                            </b>

                        </div>

                    </button>
                </template>

                <template x-if="filteredTires().length === 0">
                    <div class="tire-picker-empty">

                        <i data-lucide="search-x"></i>

                        <strong>
                            Nenhum pneu encontrado
                        </strong>

                        <p>
                            Tente buscar por outro código, marca, modelo ou medida.
                        </p>

                    </div>
                </template>

            </div>

        </div>

        <form
            method="POST"
            action="{{ route('vehicles.tires.install', $vehicle) }}"
            class="tire-picker-footer"
        >

            @csrf

            <input
                type="hidden"
                name="position_code"
                :value="selectedPositionCode"
            >

            <input
                type="hidden"
                name="tire_id"
                :value="selectedTire ? selectedTire.id : ''"
            >

            <div class="tire-picker-km">

                <label>
                    KM instalação
                </label>

                <input
                    type="number"
                    name="installed_km"
                    x-model="installedKm"
                    min="0"
                    required
                >

            </div>

            <div class="tire-picker-selected">

                <template x-if="selectedTire">
                    <div>
                        <span>
                            Pneu selecionado
                        </span>

                        <strong x-text="selectedTire.label"></strong>
                    </div>
                </template>

                <template x-if="!selectedTire">
                    <div>
                        <span>
                            Nenhum pneu selecionado
                        </span>

                        <strong>
                            Escolha um pneu na lista.
                        </strong>
                    </div>
                </template>

            </div>

            <button
                type="submit"
                class="tire-picker-confirm"
                :disabled="!selectedTire"
            >
                <i data-lucide="check-circle"></i>
                Confirmar instalação
            </button>

        </form>

    </div>
</div>

{{-- MODAL: REMOVER / TROCAR PNEU --}}
<div
    class="modal-overlay tire-remove-overlay"
    x-show="removeTireModalOpen"
    x-transition.opacity
    style="display:none;"
    @click.self="closeRemoveTireModal()"
>
    <div
        class="tire-remove-modal"
        x-transition.scale.origin.center
    >

        <div class="tire-remove-header">

            <div>
                <small>
                    Movimentação de pneu
                </small>

                <h2>
                    Remover / trocar pneu
                </h2>

                <p>
                    Pneu
                    <strong x-text="removeTireCode"></strong>
                    ·
                    posição
                    <strong x-text="removePositionCode"></strong>
                    <span x-text="removePositionLabel"></span>
                </p>
            </div>

            <button
                type="button"
                class="tire-picker-close"
                @click="closeRemoveTireModal()"
            >
                <i data-lucide="x"></i>
            </button>

        </div>

        <form
            method="POST"
            action="{{ route('vehicles.tires.remove', $vehicle) }}"
            class="tire-remove-form"
        >
            @csrf

            <input
                type="hidden"
                name="position_code"
                :value="removePositionCode"
            >

            <input
                type="hidden"
                name="tire_id"
                :value="removeTireId"
            >

            <div class="tire-remove-grid">

                <div class="form-group">
                    <label>
                        KM de remoção
                    </label>

                    <input
                        type="number"
                        name="removed_km"
                        x-model="removeKm"
                        min="0"
                        required
                    >
                </div>

                <div class="form-group">
                    <label>
                        Destino do pneu
                    </label>

                    <select
                        name="destination"
                        x-model="removeDestination"
                        required
                    >
                        <option value="available">
                            Voltar para estoque
                        </option>

                        <option value="maintenance">
                            Enviar para manutenção/reforma
                        </option>

                        <option value="discarded">
                            Descartar pneu
                        </option>
                    </select>
                </div>

                <div class="form-group">
                    <label>
                        Motivo
                    </label>

                    <select
                        name="removal_reason"
                        x-model="removeReason"
                        required
                    >
                        <option value="">
                            Selecione
                        </option>

                        <option value="Rodízio">
                            Rodízio
                        </option>

                        <option value="Substituição por desgaste">
                            Substituição por desgaste
                        </option>

                        <option value="Furo / dano">
                            Furo / dano
                        </option>

                        <option value="Recapagem / reforma">
                            Recapagem / reforma
                        </option>

                        <option value="Descarte">
                            Descarte
                        </option>

                        <option value="Outro">
                            Outro
                        </option>
                    </select>
                </div>

            </div>

            <div class="form-group">
                <label>
                    Observações
                </label>

                <textarea
                    name="notes"
                    rows="3"
                    placeholder="Detalhes adicionais da remoção, troca ou descarte..."
                ></textarea>
            </div>

            <div class="tire-remove-warning">
                <i data-lucide="info"></i>

                <span>
                    Ao confirmar, a posição ficará sem pneu. Para concluir uma troca, instale outro pneu disponível na mesma posição.
                </span>
            </div>

            <div class="tire-remove-footer">

                <button
                    type="button"
                    class="tire-remove-cancel"
                    @click="closeRemoveTireModal()"
                >
                    Cancelar
                </button>

                <button
                    type="submit"
                    class="tire-remove-confirm"
                >
                    <i data-lucide="check-circle"></i>
                    Confirmar remoção
                </button>

            </div>

        </form>

    </div>
</div>

</div>
<script>
function vehicleTiresPage() {
    return {
        removeTireModalOpen: false,
        
        removePositionCode: null,
        
        removePositionLabel: null,
        
        removeTireId: null,
        
        removeTireCode: null,
        
        removeKm: 0,
        
        removeDestination: 'available',
        
        removeReason: '',
        tirePickerOpen: false,
        tireSearch: '',

        selectedPositionCode: null,

        selectedPositionLabel: null,

        selectedTire: null,

        installedKm: 0,

        availableTires: @json($availableTires),
        
        openRemoveTireModal(positionCode, positionLabel, tireId, tireCode, currentKm) {
            this.removePositionCode =
                positionCode;
        
            this.removePositionLabel =
                positionLabel;
        
            this.removeTireId =
                tireId;
        
            this.removeTireCode =
                tireCode;
        
            this.removeKm =
                currentKm || 0;
        
            this.removeDestination =
                'available';
        
            this.removeReason =
                '';
        
            this.removeTireModalOpen =
                true;
        
            this.$nextTick(() => {
                if (window.lucide) {
                    lucide.createIcons();
                }
            });
        },
        
        closeRemoveTireModal() {
            this.removeTireModalOpen =
                false;
        },
        
        openTirePicker(positionCode, positionLabel, currentKm) {
            this.selectedPositionCode =
                positionCode;

            this.selectedPositionLabel =
                positionLabel;

            this.installedKm =
                currentKm || 0;

            this.selectedTire =
                null;

            this.tireSearch =
                '';

            this.tirePickerOpen =
                true;

            this.$nextTick(() => {
                if (window.lucide) {
                    lucide.createIcons();
                }

                const input =
                    document.querySelector('.tire-picker-search input');

                if (input) {
                    input.focus();
                }
            });
        },

        closeTirePicker() {
            this.tirePickerOpen =
                false;
        },

        selectTire(tire) {
            this.selectedTire =
                tire;

            this.$nextTick(() => {
                if (window.lucide) {
                    lucide.createIcons();
                }
            });
        },

        filteredTires() {
            const term =
                String(this.tireSearch || '')
                    .toLowerCase()
                    .trim();

            return this.availableTires
                .filter(function (tire) {

                    if (!term) {
                        return true;
                    }

                    return [
                        tire.code,
                        tire.brand,
                        tire.model,
                        tire.size,
                    ]
                        .join(' ')
                        .toLowerCase()
                        .includes(term);
                })
                .slice(0, 50);
        },
    };
}
</script>
@endsection
