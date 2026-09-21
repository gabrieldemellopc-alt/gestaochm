@extends('layouts.app')
@push('styles')
<link
    rel="stylesheet"
    href="{{ asset('css/pages/vehicles.css') }}?v=3"
>
@endpush
@section('content')
@php
    $vehicleTypes = \App\Models\Vehicle::typeOptions();
    $selectedType = old('type', $vehicle->type ?? 'automovel');
    $selectedIcon = \App\Models\Vehicle::iconForType($selectedType);
@endphp
<div class="vehicle-edit-header vehicle-edit-page">
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
            <i class="bi bi-arrow-left"></i>
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
    @if ($errors->any())
        <div class="vehicle-form-errors">
            <div class="vehicle-form-errors__title">
                <i class="bi bi-exclamation-triangle"></i>
                Não foi possível salvar o veículo
            </div>

            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    @method('PUT')
    <p class="form-required-note"><span class="required-mark">*</span> Campos obrigatórios</p>
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
                Divisão <span class="required-mark">*</span>
            </label>
            <select
                name="division_id"
                id="vehicleDivisionSelect"
                class="form-input"
                required
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
                Localidade <span class="required-mark">*</span>
            </label>
            <select
                name="location_id"
                id="vehicleLocationSelect"
                class="form-input"
                required
            >
                @foreach($locations as $location)
                    <option
                        value="{{ $location->id }}"
                        data-division-id="{{ $location->division_id }}"
                        @selected(old('location_id', $vehicle->location_id) == $location->id)
                    >
                        {{ $location->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="form-group">
            <label>
                Tipo <span class="required-mark">*</span>
            </label>
            <select
                name="type"
                id="vehicleTypeSelect"
                class="form-input"
                required
            >
                @foreach($vehicleTypes as $value => $type)
                    <option value="{{ $value }}" @selected(old('type', $vehicle->type) === $value)>{{ $type['label'] }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group">
            <label>Vínculo com a frota</label>
            <select name="fleet_relation" class="form-input">
                <option value="internal" @selected(old('fleet_relation', $vehicle->fleet_relation ?? 'internal') === 'internal')>Frota interna</option>
                <option value="aggregated" @selected(old('fleet_relation', $vehicle->fleet_relation) === 'aggregated')>Agregado</option>
                <option value="rented" @selected(old('fleet_relation', $vehicle->fleet_relation) === 'rented')>Alugado</option>
            </select>
        </div>
    </div>
</div>
<div class="vehicle-edit-dual-grid">

    <div class="edit-card vehicle-operation-card">

        <div class="card-header">
            <div>
                <h3>Operação</h3>
                <p class="card-description">
                    Leituras, situação cadastral e histórico de atividade.
                </p>
            </div>
        </div>

        <div class="vehicle-operation-grid">

            <div class="form-group">
                <label>Hodômetro atual</label>

                <input
                    type="number"
                    name="current_km"
                    class="form-input"
                    value="{{ $vehicle->current_km }}"
                    min="{{ $vehicle->current_km ?? 0 }}"
                >
            </div>

            <div class="form-group">
                <label>Horímetro atual</label>

                <input
                    type="number"
                    name="current_hours"
                    class="form-input"
                    value="{{ $vehicle->current_hours }}"
                    min="{{ $vehicle->current_hours ?? 0 }}"
                    step="1"
                >
            </div>

            <div class="form-group">
                <label>
                    Situação do cadastro
                    <span class="required-mark">*</span>
                </label>

                <select
                    name="status"
                    id="vehicleRegistrationStatus"
                    class="form-input"
                    required
                >
                    <option
                        value="active"
                        @selected(
                            old('status', $vehicle->status) === 'active'
                        )
                    >
                        Ativo
                    </option>

                    <option
                        value="inactive"
                        @selected(
                            old('status', $vehicle->status) === 'inactive'
                        )
                    >
                        Inativo
                    </option>
                </select>

                @if($openMaintenance)
                    <small class="vehicle-status-maintenance-warning">
                        <i class="bi bi-tools"></i>
                        Há uma manutenção em andamento. O veículo não poderá ser inativado até o encerramento da manutenção.
                    </small>
                @endif
            </div>

            <div class="form-group">
                <label>Início de operação</label>

                <input
                    type="date"
                    name="operation_started_at"
                    class="form-input"
                    value="{{
                        old(
                            'operation_started_at',
                            optional(
                                $vehicle->operation_started_at
                            )->format('Y-m-d')
                        )
                    }}"
                >
            </div>

        </div>


        <div class="vehicle-operation-history">

            <div class="vehicle-operation-history__title">
                <span>Histórico de atividade</span>

                @if($vehicle->status === 'inactive')
                    <span class="vehicle-operation-history__badge is-inactive">
                        Inativo
                    </span>
                @else
                    <span class="vehicle-operation-history__badge is-active">
                        Ativo
                    </span>
                @endif
            </div>


            <div class="vehicle-operation-history__timeline">

                @if($statusHistory->isEmpty() && $vehicle->operation_started_at)
                    <div class="vehicle-operation-history__item is-active">
                        <span class="vehicle-operation-history__dot"></span>

                        <div>
                            <strong>Ativo</strong>
                            <small>
                                Desde o início da operação:
                                {{ $vehicle->operation_started_at->format('d/m/Y') }}
                            </small>

                            @if($period->changer)
                                <small class="vehicle-operation-history__user">
                                    <i class="bi bi-person"></i>
                                    Alterado por {{ $period->changer->name }}
                                </small>
                            @endif
                        </div>
                    </div>
                @endif


                @forelse($statusHistory->take(6) as $period)

                    @php
                        $periodLabel = match($period->status) {
                            'inactive' => 'Inativo',
                            'active' => 'Ativo',
                            default => ucfirst((string) $period->status),
                        };

                        $periodDuration = null;

                        if ($period->started_at) {
                            $durationEnd =
                                $period->ended_at ?? now();

                            $totalMinutes = max(
                                0,
                                (int) floor(
                                    $period->started_at->diffInMinutes(
                                        $durationEnd
                                    )
                                )
                            );

                            $days = intdiv($totalMinutes, 1440);
                            $hours = intdiv(
                                $totalMinutes % 1440,
                                60
                            );
                            $minutes = $totalMinutes % 60;

                            $parts = [];

                            if ($days > 0) {
                                $parts[] =
                                    $days
                                    . ' dia'
                                    . ($days !== 1 ? 's' : '');
                            }

                            if ($hours > 0) {
                                $parts[] =
                                    $hours . ' h';
                            }

                            if (
                                $days === 0
                                && $minutes > 0
                            ) {
                                $parts[] =
                                    $minutes . ' min';
                            }

                            $periodDuration =
                                count($parts)
                                    ? implode(' ', $parts)
                                    : 'menos de 1 min';
                        }
                    @endphp

                    <div class="vehicle-operation-history__item {{ $period->status === 'active' ? 'is-active' : 'is-inactive' }}">
                        <span class="vehicle-operation-history__dot"></span>

                        <div>
                            <strong>{{ $periodLabel }}</strong>

                            <small>
                                @if($period->ended_at)

                                    {{ $period->started_at?->format('d/m/Y H:i') }}
                                    →
                                    {{ $period->ended_at->format('d/m/Y H:i') }}

                                    @if($periodDuration)
                                        · {{ $periodDuration }}
                                    @endif

                                @else

                                    Desde
                                    {{ $period->started_at?->format('d/m/Y H:i') }}

                                    @if($periodDuration)
                                        · há {{ $periodDuration }}
                                    @endif

                                @endif
                            </small>

                            @if($period->changer)
                                <small class="vehicle-operation-history__user">
                                    <i class="bi bi-person"></i>
                                    Alterado por {{ $period->changer->name }}
                                </small>
                            @endif
                        </div>
                    </div>


                @empty

                    @unless($vehicle->operation_started_at)
                        <div class="vehicle-operation-history__empty">
                            Nenhuma alteração operacional registrada.
                        </div>
                    @endunless

                @endforelse

            </div>

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
            <div class="form-grid vehicle-data-grid">
                <div class="form-group">
                    <label>
                        Nome <span class="required-mark">*</span>
                    </label>
                    <input
                        type="text"
                        name="name"
                        class="form-input"
                        value="{{ $vehicle->name }}"
                        required
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
                <div class="form-group"><label>RENAVAM</label><input type="text" name="renavam" class="form-input" placeholder="Ex: 01234567890" value="{{ old('renavam', $vehicle->renavam) }}"></div>
                <div class="form-group"><label>Nº de série</label><input type="text" name="serial_number" class="form-input" placeholder="Ex: CAT123456 ou 8A12345" value="{{ old('serial_number', $vehicle->serial_number) }}"></div>
                <div class="form-group"><label>CHASSI</label><input type="text" name="chassis" class="form-input" placeholder="Ex: 9BWZZZ377VT004251" value="{{ old('chassis', $vehicle->chassis) }}"></div>
            </div>
        </div>
        </div>
        
        @include('vehicle.partials.operational-controls', ['vehicle' => $vehicle])

        {{-- CONTROLE PREVENTIVO --}}
        <div class="edit-card vehicle-full-card">
        
            <div class="card-header">
        
                <div>
                    <h3>
                        Controle preventivo
                    </h3>
        
                    <p class="card-description">
                        Defina a formação e o controle preventivo de pneus do veículo.
                    </p>
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
        
        
        <div class="edit-card vehicle-full-card vehicle-fuel-products">
            <div class="card-header"><h3>Combustível do veículo</h3><p class="card-description">Defina quais combustíveis este veículo pode utilizar.</p></div>
            <div class="procedures-grid">@foreach($fuelProducts as $product)<label class="procedure-pill"><input type="checkbox" name="fuel_product_ids[]" value="{{ $product->id }}" data-fuel-slug="{{ $product->slug }}" @checked(in_array($product->id, old('fuel_product_ids', $vehicle->fuelProducts->pluck('id')->all())))><span>{{ $product->name }}</span></label>@endforeach</div>
            <small class="form-help">Diesel S10 e Diesel S500 são exclusivos. Gasolina e Álcool podem ser combinados para veículo flex.</small>
        </div>
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            const inputs = Array.from(document.querySelectorAll('.vehicle-fuel-products input[type="checkbox"]'));
            const dieselSlugs = ['diesel-s10', 'diesel-s500'];

            function syncFuelChoices() {
                inputs.forEach((input) => {
                    input.disabled = false;
                    input.closest('.procedure-pill')?.classList.remove('is-disabled');
                });
            }

            inputs.forEach((input) => input.addEventListener('change', function () {
                if (!this.checked) {
                    syncFuelChoices();
                    return;
                }

                if (dieselSlugs.includes(this.dataset.fuelSlug)) {
                    inputs.forEach((other) => {
                        if (other !== this) other.checked = false;
                    });
                } else {
                    inputs.forEach((other) => {
                        if (dieselSlugs.includes(other.dataset.fuelSlug)) other.checked = false;
                    });
                }
                syncFuelChoices();
            }));

            syncFuelChoices();
        });
        </script>
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
        @include('vehicle.partials.procedures-selector')
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
                <i class="bi bi-floppy"></i>
        
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
        if (plateInput.value && !isValidPlate(plateInput.value)) {
            event.preventDefault();
            showPlateError();
            plateInput.focus();
            return false;
        }
        hidePlateError();

        if (!plateInput.value && !window.confirm('Este veículo será salvo sem placa. Confirma que ele realmente não possui placa?')) {
            event.preventDefault();
            return false;
        }
    });
});
</script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const typeSelect =
        document.getElementById('vehicleTypeSelect');
    const typePreview =
        document.getElementById('vehicleTypePreview');
    const vehicleTypeIcons = @json(collect($vehicleTypes)->map(fn ($type) => asset('images/'.$type['icon'])));
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
<script>
document.addEventListener('DOMContentLoaded', () => {
    const tireControl =
        document.querySelector(
            'input[type="checkbox"][name="tire_control_enabled"]'
        );

    const tireLayout =
        document.querySelector('.tire-layout-group');

    if (!tireControl || !tireLayout) {
        return;
    }

    function syncTireLayoutState() {
        tireLayout.classList.toggle(
            'is-control-disabled',
            !tireControl.checked
        );
    }

    tireControl.addEventListener(
        'change',
        syncTireLayoutState
    );

    syncTireLayoutState();
});
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const divisionSelect = document.getElementById('vehicleDivisionSelect');
    const locationSelect = document.getElementById('vehicleLocationSelect');

    if (!divisionSelect || !locationSelect) {
        return;
    }

    function filterLocations() {
        const divisionId = divisionSelect.value;
        let selectedLocationIsAvailable = false;

        Array.from(locationSelect.options).forEach(function (option) {
            const available = option.dataset.divisionId === divisionId;
            option.hidden = !available;
            option.disabled = !available;
            selectedLocationIsAvailable ||= available && option.selected;
        });

        if (!selectedLocationIsAvailable) {
            const firstAvailable = Array.from(locationSelect.options)
                .find(function (option) { return !option.disabled; });

            if (firstAvailable) {
                locationSelect.value = firstAvailable.value;
            }
        }
    }

    divisionSelect.addEventListener('change', filterLocations);
    filterLocations();
});
</script>

@endsection
