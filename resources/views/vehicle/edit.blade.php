@extends('layouts.app')

@push('styles')
<link
    rel="stylesheet"
    href="{{ asset('css/pages/vehicles.css') }}"
>
@endpush

@section('content')
@php
    $vehicleTypeIcons = [
        'automovel' => 'automovel.png',
        'prancha'   => 'prancha.png',
        'lixo'      => 'lixo.png',
        'cacamba'   => 'cacamba.png',
        'bau'       => 'bau.png',
        'trator'    => 'trator.png',
    ];

    $selectedType = old('type', $vehicle->type ?? 'automovel');
    $selectedIcon = $vehicleTypeIcons[$selectedType] ?? 'automovel.png';
@endphp
<div class="vehicle-edit-header">

    <div>

        <span class="vehicles-kicker">
            Operacional
        </span>

        <h1>
            Editar veículo
        </h1>
        
        <p>
            {{ $vehicle->plate }} · {{ $vehicle->name }}
        </p>

    </div>

    <div class="vehicles-header-actions">

        <a
            href="{{ route('vehicles.index') }}"
            class="chm-page-button secondary"
        >
            <i data-lucide="arrow-left"></i>

            Voltar para veículos
        </a>

    </div>

</div>

<form
    action="{{ route('vehicles.update', $vehicle->id) }}"
    method="POST"
    class="vehicle-edit-page"
    id="vehicleEditForm"
>

    @csrf
    @method('PUT')

{{-- HERO --}}
<div class="vehicle-edit-hero vehicle-edit-hero--compact">

    <div class="vehicle-edit-hero-icon-col">

        <div class="vehicle-edit-avatar vehicle-edit-avatar--large">

            <img
                id="vehicleTypePreview"
                src="{{ asset('images/' . $selectedIcon) }}"
                alt="Tipo do veículo"
            >

        </div>

    </div>

    <div class="vehicle-edit-hero-fields">

        <div class="form-group">

            <label>
                Divisão
            </label>

            <select
                name="division_id"
                class="form-input"
            >

                @foreach($divisions as $division)

                    <option
                        value="{{ $division->id }}"
                        @selected(old('division_id', $vehicle->division_id) == $division->id)
                    >
                        {{ $division->name }}
                    </option>

                @endforeach

            </select>

        </div>

        <div class="form-group">

            <label>
                Localidade
            </label>

            <select
                name="location_id"
                class="form-input"
            >

                @foreach($locations as $location)

                    <option
                        value="{{ $location->id }}"
                        @selected(old('location_id', $vehicle->location_id) == $location->id)
                    >
                        {{ $location->name }}
                    </option>

                @endforeach

            </select>

        </div>

        <div class="form-group">

            <label>
                Tipo
            </label>

            <select
                name="type"
                id="vehicleTypeSelect"
                class="form-input"
            >

                <option
                    value="automovel"
                    @selected(old('type', $vehicle->type) == 'automovel')
                >
                    Automóvel
                </option>

                <option
                    value="prancha"
                    @selected(old('type', $vehicle->type) == 'prancha')
                >
                    Prancha
                </option>

                <option
                    value="lixo"
                    @selected(old('type', $vehicle->type) == 'lixo')
                >
                    Caminhão de lixo
                </option>

                <option
                    value="cacamba"
                    @selected(old('type', $vehicle->type) == 'cacamba')
                >
                    Caçamba
                </option>

                <option
                    value="bau"
                    @selected(old('type', $vehicle->type) == 'bau')
                >
                    Baú
                </option>

                <option
                    value="trator"
                    @selected(old('type', $vehicle->type) == 'trator')
                >
                    Trator
                </option>

            </select>

        </div>

    </div>

</div>

<div class="vehicle-edit-dual-grid">
    <div class="edit-card vehicle-full-card">
            <h3>
                Operação
            </h3>

            <div class="form-group">

                <label>
                    Hodômetro atual
                </label>

                <input
                    type="number"
                    name="current_km"
                    class="form-input"
                    value="{{ $vehicle->current_km }}"
                    min="{{ $vehicle->current_km ?? 0 }}"
                >

            </div>

            <div class="form-group">

                <label>
                    Horímetro atual
                </label>

                <input
                    type="number"
                    name="current_hours"
                    class="form-input"
                    value="{{ $vehicle->current_hours }}"
                    min="{{ $vehicle->current_hours ?? 0 }}"
                    step="0.1"
                >

            </div>

            <div class="form-group">

                <label>
                    Status 
                </label>

                <select
                    name="operational_status"
                    class="form-input"
                >

                    <option
                        value="operational"
                        @selected(
                            $vehicle->operational_status == 'operational'
                        )
                    >
                        Operacional
                    </option>

                    <option
                        value="maintenance"
                        @selected(
                            $vehicle->operational_status == 'maintenance'
                        )
                    >
                        Em manutenção
                    </option>


                </select>

            </div>

        </div>


    {{-- CONTEÚDO --}}
        {{-- DADOS GERAIS --}}
        <div class="edit-card">

            <div class="card-header">

                <h3>
                    Dados do veículo
                </h3>

            </div>

            <div class="form-grid">

                <div class="form-group">

                    <label>
                        Nome
                    </label>

                    <input
                        type="text"
                        name="name"
                        class="form-input"
                        value="{{ $vehicle->name }}"
                    >

                </div>

                <div class="form-group">

                    <label>
                        Placa
                    </label>

                    <input
                        type="text"
                        name="plate"
                        id="plateInput"
                        class="form-input @error('plate') input-invalid @enderror"
                        value="{{ old('plate', $vehicle->plate) }}"
                        maxlength="8"
                        placeholder="ABC-1D23"
                        autocomplete="off"
                        inputmode="text"
                    >
                    
                    <small
                        id="plateError"
                        class="form-error"
                        style="{{ $errors->has('plate') ? 'display:block;' : 'display:none;' }}"
                    >
                        @error('plate')
                            {{ $message }}
                        @else
                            A placa deve estar no formato ABC-1D23.
                        @enderror
                    </small>
                    

                </div>

                <div class="form-group">

                    <label>
                        Marca
                    </label>

                    <input
                        type="text"
                        name="brand"
                        class="form-input"
                        value="{{ $vehicle->brand }}"
                    >

                </div>

                <div class="form-group">

                    <label>
                        Modelo
                    </label>

                    <input
                        type="text"
                        name="model"
                        class="form-input"
                        value="{{ $vehicle->model }}"
                    >

                </div>

                <div class="form-group">

                    <label>
                        Ano
                    </label>

                    <input
                        type="number"
                        name="year"
                        class="form-input"
                        value="{{ $vehicle->year }}"
                    >

                </div>

                <div class="form-group">
                
                    <label>
                        Código patrimonial
                    </label>
                
                    <input
                        type="text"
                        name="asset_code"
                        class="form-input"
                        value="{{ old('asset_code', $vehicle->asset_code ?? '') }}"
                        placeholder="Ex: PAT-001, VEIC-12"
                    >
                
                </div>

            </div>

        </div>
        </div>
        
        {{-- CONTROLE PREVENTIVO --}}
        <div class="edit-card vehicle-full-card">
        
            <div class="card-header">
        
                <div>
                    <h3>
                        Controle preventivo
                    </h3>
        
                    <p class="card-description">
                        Defina a operação, situação e formação de pneus do veículo.
                    </p>
                </div>
        
            </div>
        
            <div class="form-grid">
        
                <div class="form-group">
        
                    <label>
                        Início de operação
                    </label>
        
                    <input
                        type="date"
                        name="operation_started_at"
                        class="form-input"
                        value="{{
                            old(
                                'operation_started_at',
                                optional($vehicle->operation_started_at)->format('Y-m-d')
                            )
                        }}"
                    >
        
                </div>
        
                <div class="form-group">
        
                    <label>
                        Situação
                    </label>
        
                    <select
                        name="status"
                        class="form-input"
                    >
        
                        <option
                            value="active"
                            @selected(old('status', $vehicle->status) == 'active')
                        >
                            Ativo
                        </option>
        
                        <option
                            value="inactive"
                            @selected(old('status', $vehicle->status) == 'inactive')
                        >
                            Inativo
                        </option>
        
                    </select>
        
                </div>
        
            </div>
        
            <div class="form-group tire-layout-group">
        
                <label>
                    Formação de pneus
                </label>
        
                <div class="tire-layout-selector">
        
                    <label class="tire-layout-option">
                        <input
                            type="radio"
                            name="tire_layout"
                            value="car_4_single"
                            @checked(old('tire_layout', $vehicle->tire_layout ?? 'truck_6_mixed') == 'car_4_single')
                        >
        
                        <span class="tire-layout-card">
        
                            @include('vehicle.partials.tire-layout-svg', [
                                'layout' => 'car_4_single'
                            ])
        
                            <strong>
                                4 pneus
                            </strong>
        
                            <small>
                                2 eixos simples
                            </small>
        
                        </span>
                    </label>
        
                    <label class="tire-layout-option">
                        <input
                            type="radio"
                            name="tire_layout"
                            value="truck_6_mixed"
                            @checked(old('tire_layout', $vehicle->tire_layout ?? 'truck_6_mixed') == 'truck_6_mixed')
                        >
        
                        <span class="tire-layout-card">
        
                            @include('vehicle.partials.tire-layout-svg', [
                                'layout' => 'truck_6_mixed'
                            ])
        
                            <strong>
                                6 pneus
                            </strong>
        
                            <small>
                                1 eixo simples + 1 eixo duplo
                            </small>
        
                        </span>
                    </label>
        
                    <label class="tire-layout-option">
                        <input
                            type="radio"
                            name="tire_layout"
                            value="truck_8_mixed"
                            @checked(old('tire_layout', $vehicle->tire_layout ?? 'truck_6_mixed') == 'truck_8_mixed')
                        >
        
                        <span class="tire-layout-card">
        
                            @include('vehicle.partials.tire-layout-svg', [
                                'layout' => 'truck_8_mixed'
                            ])
        
                            <strong>
                                8 pneus
                            </strong>
        
                            <small>
                                2 eixos simples + 1 eixo duplo
                            </small>
        
                        </span>
                    </label>
        
                    <label class="tire-layout-option">
                        <input
                            type="radio"
                            name="tire_layout"
                            value="truck_10_mixed"
                            @checked(old('tire_layout', $vehicle->tire_layout ?? 'truck_6_mixed') == 'truck_10_mixed')
                        >
        
                        <span class="tire-layout-card">
        
                            @include('vehicle.partials.tire-layout-svg', [
                                'layout' => 'truck_10_mixed'
                            ])
        
                            <strong>
                                10 pneus
                            </strong>
        
                            <small>
                                1 eixo simples + 2 eixos duplos
                            </small>
        
                        </span>
                    </label>
        
                    <label class="tire-layout-option">
                        <input
                            type="radio"
                            name="tire_layout"
                            value="truck_12_mixed"
                            @checked(old('tire_layout', $vehicle->tire_layout ?? 'truck_6_mixed') == 'truck_12_mixed')
                        >
        
                        <span class="tire-layout-card">
        
                            @include('vehicle.partials.tire-layout-svg', [
                                'layout' => 'truck_12_mixed'
                            ])
        
                            <strong>
                                12 pneus
                            </strong>
        
                            <small>
                                2 eixos simples + 2 eixos duplos
                            </small>
        
                        </span>
                    </label>
        
                </div>
        
                <small class="form-help">
                    Essa configuração define quais posições de pneus serão geradas no controle do veículo.
                </small>
        
            </div>
        
        </div>
        
        
        {{-- OBSERVAÇÕES --}}
        <div class="edit-card vehicle-full-card">
        
            <div class="card-header">
        
                <h3>
                    Observações
                </h3>
        
            </div>
        
            <textarea
                name="notes"
                rows="5"
                class="form-input"
                placeholder="Observações gerais do veículo..."
            >{{ old('notes', $vehicle->notes) }}</textarea>
        
        </div>
        {{-- PROCEDIMENTOS --}}
        <div class="edit-card vehicle-full-card">        
            <div class="card-header">
        
                <h3>
                    Procedimentos aplicáveis
                </h3>
        
                <p class="card-description">
        
                    Selecione quais procedimentos
                    este veículo poderá executar.
        
                </p>
        
            </div>
        
            <div class="procedures-grid">
        
                @foreach($procedures as $procedure)
        
                    <label class="procedure-pill">
        
                        <input
                            type="checkbox"
                            name="procedures[]"
                            value="{{ $procedure->id }}" @checked(
                                    $vehicle->procedures
                                    ->contains($procedure->id)
                                )
                        >
        
                        <span>
        
                            {{ $procedure->name }}
        
                        </span>
        
                    </label>
        
                @endforeach
        
            </div>
        
        </div>

        {{-- ACTIONS --}}
        <div class="vehicle-edit-actions">
        
            <a
                href="{{ route('vehicles.index') }}"
                class="chm-page-button secondary"
            >
                Cancelar
            </a>
        
            <button
                type="submit"
                class="chm-page-button primary"
            >
                <i data-lucide="save"></i>
        
                Salvar alterações
            </button>
        
        </div>


</form>
<script>
document.addEventListener('DOMContentLoaded', () => {

    const form =
        document.getElementById('vehicleEditForm');

    const plateInput =
        document.getElementById('plateInput');

    const plateError =
        document.getElementById('plateError');

    if (!form || !plateInput) {
        return;
    }

    function maskPlate(value) {

        value = String(value || '')
            .toUpperCase()
            .replace(/[^A-Z0-9]/g, '');

        let firstPart =
            value
                .slice(0, 3)
                .replace(/[^A-Z]/g, '');

        let secondRaw =
            value
                .slice(3)
                .replace(/[^A-Z0-9]/g, '')
                .slice(0, 4);

        if (firstPart.length === 3) {
            return firstPart + (
                secondRaw.length
                    ? '-' + secondRaw
                    : '-'
            );
        }

        return firstPart;
    }

    function isValidPlate(value) {

        return /^[A-Z]{3}-[A-Z0-9]{4}$/.test(value);
    }

    function showPlateError() {

        if (plateError) {
            plateError.style.display = 'block';
            plateError.textContent =
                'A placa deve estar no formato ABC-1D23.';
        }

        plateInput.classList.add('input-invalid');
    }

    function hidePlateError() {

        if (plateError) {
            plateError.style.display = 'none';
        }

        plateInput.classList.remove('input-invalid');
    }

    plateInput.addEventListener('input', () => {

        const oldLength =
            plateInput.value.length;

        plateInput.value =
            maskPlate(plateInput.value);

        const newLength =
            plateInput.value.length;

        plateInput.selectionStart =
            plateInput.selectionEnd =
            newLength;

        hidePlateError();
    });

    plateInput.addEventListener('blur', () => {

        plateInput.value =
            maskPlate(plateInput.value);

        if (
            plateInput.value &&
            !isValidPlate(plateInput.value)
        ) {
            showPlateError();
        }
    });

    form.addEventListener('submit', (event) => {

        plateInput.value =
            maskPlate(plateInput.value);

        if (!isValidPlate(plateInput.value)) {

            event.preventDefault();

            showPlateError();

            plateInput.focus();

            return false;
        }

        hidePlateError();
    });

});
</script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const typeSelect =
        document.getElementById('vehicleTypeSelect');

    const typePreview =
        document.getElementById('vehicleTypePreview');

    const vehicleTypeIcons = {
        automovel: "{{ asset('images/automovel.png') }}",
        prancha: "{{ asset('images/prancha.png') }}",
        lixo: "{{ asset('images/lixo.png') }}",
        cacamba: "{{ asset('images/cacamba.png') }}",
        bau: "{{ asset('images/bau.png') }}",
        trator: "{{ asset('images/trator.png') }}",
    };

    function updateVehicleTypePreview() {
        if (
            ! typeSelect
            ||
            ! typePreview
        ) {
            return;
        }

        const selectedType =
            typeSelect.value;

        if (vehicleTypeIcons[selectedType]) {
            typePreview.src =
                vehicleTypeIcons[selectedType];
        }
    }

    if (
        typeSelect
        &&
        typePreview
    ) {
        typeSelect.addEventListener(
            'change',
            updateVehicleTypePreview
        );

        updateVehicleTypePreview();
    }
});
</script>
@endsection