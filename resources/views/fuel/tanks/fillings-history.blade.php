@extends('layouts.app')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/pages/fuel.css') }}?v=3">
@endpush
@section('content')
<main
    class="fuel-page fuel-history-page"
    x-data="{
        cancelId: null,
        editId: @js(session('edit_filling_id'))
    }"
>
@php
    $hasFilters = request()->filled([
        'start_date',
        'end_date',
        'vehicle_id',
        'fleet_relation',
        'fuel_product_id',
        'fuel_tank_id',
        'source',
        'status',
    ]);
@endphp
<header class="fuel-header fuel-history-header">

    <div>
        <span class="fuel-kicker">Abastecimentos</span>
        <h1>Histórico completo de saídas</h1>
        <p>Consulte lançamentos e cancelamentos da unidade ativa.</p>
    </div>

    <div class="fuel-history-header-actions">

        <a
            href="{{ route('fuel.tanks.index') }}"
            class="fuel-secondary-action"
        >
            <i class="bi bi-arrow-left"></i>
            Voltar
        </a>

        <a
            href="{{ route('fuel.fillings.history', [
                'start_date' => now()->toDateString(),
                'end_date' => now()->toDateString(),
                'fleet_relation' => $fleetRelation,
                'status' => 'active',
            ]) }}"
            class="fuel-history-today-shortcut"
            title="Exibir todos os abastecimentos realizados hoje"
        >
            <i class="bi bi-calendar-check"></i>

            <span>
                <small>Atalho</small>
                Ver todos os abastecimentos de hoje
            </span>
        </a>

    </div>

</header>
<form
    id="fuelHistoryFilters"
    class="fuel-history-filters"
    method="GET"
>

<div class="fuel-history-filter fuel-history-filter-vehicle">
<label>Veículo</label>
<div
    class="fuel-vehicle-search"
    x-data="{
        open: false,
        search: @js(optional($vehicles->firstWhere('id', (int) request('vehicle_id')))->name
            ? optional($vehicles->firstWhere('id', (int) request('vehicle_id')))->name . ' · ' . optional($vehicles->firstWhere('id', (int) request('vehicle_id')))->plate
            : ''),
        selectedId: @js(request('vehicle_id')),
        vehicles: @js($vehicles->map(function ($vehicle) {
            return [
                'id' => $vehicle->id,
                'label' => trim(($vehicle->name ?? '') . ' · ' . ($vehicle->plate ?? '')),
                'search' => strtolower(trim(
                    ($vehicle->name ?? '') . ' ' .
                    ($vehicle->plate ?? '') . ' ' .
                    ($vehicle->asset_code ?? '') . ' ' .
                    ($vehicle->renavam ?? '') . ' ' .
                    ($vehicle->serial_number ?? '') . ' ' .
                    ($vehicle->brand ?? '') . ' ' .
                    ($vehicle->model ?? '') . ' ' .
                    ($vehicle->year ?? '')
                )),
                'secondary' => filled($vehicle->asset_code)
                    ? 'Código: ' . $vehicle->asset_code
                    : (filled($vehicle->renavam)
                        ? 'RENAVAM: ' . $vehicle->renavam
                        : (filled($vehicle->serial_number)
                            ? 'Série: ' . $vehicle->serial_number
                            : null)),
            ];
        })->values()),
        get filteredVehicles() {
            const term = this.search.trim().toLowerCase();

            if (!term) {
                return this.vehicles.slice(0, 10);
            }

            return this.vehicles
                .filter(vehicle => vehicle.search.includes(term))
                .slice(0, 10);
        },
        selectVehicle(vehicle) {
            this.selectedId = vehicle.id;
            this.search = vehicle.label;
            this.open = false;

            this.$nextTick(() => {
                const form = document.getElementById('fuelHistoryFilters');

                if (form) {
                    form.requestSubmit();
                }
            });
        },
        clearVehicle() {
            this.selectedId = '';
            this.search = '';
            this.open = false;
        }
    }"
    x-on:click.outside="open = false"
>
    <input type="hidden" name="vehicle_id" x-model="selectedId">

    <div class="fuel-vehicle-search-input">
        <i class="bi bi-search"></i>

        <input
            type="text"
            x-model="search"
            x-on:focus="open = true"
            x-on:input="
                open = true;
                if (selectedId) {
                    selectedId = '';
                }
            "
            placeholder="Nome, placa, código, RENAVAM, série..."
            autocomplete="off"
        >

        <button
            type="button"
            class="fuel-vehicle-search-clear"
            x-show="search"
            x-on:click="clearVehicle()"
            aria-label="Limpar veículo"
        >
            <i class="bi bi-x-lg"></i>
        </button>
    </div>

    <div
        class="fuel-vehicle-search-results"
        x-show="open"
        x-transition
        x-cloak
    >
        <template x-for="vehicle in filteredVehicles" :key="vehicle.id">
            <button
                type="button"
                class="fuel-vehicle-search-option"
                x-on:click="selectVehicle(vehicle)"
            >
                <span x-text="vehicle.label"></span>
                <small x-show="vehicle.secondary" x-text="vehicle.secondary"></small>
            </button>
        </template>

        <div
            class="fuel-vehicle-search-empty"
            x-show="filteredVehicles.length === 0"
        >
            Nenhum veículo encontrado.
        </div>
    </div>
</div>
</div>

<div class="fuel-history-filter fuel-history-filter-relation">
<label>Vínculo</label>

<div class="fuel-history-relation-toggle">

    <label>
        <input
            type="radio"
            name="fleet_relation"
            value="internal"
            @checked($fleetRelation === 'internal')
        >
        <span>Interno</span>
    </label>

    <label>
        <input
            type="radio"
            name="fleet_relation"
            value="aggregated"
            @checked($fleetRelation === 'aggregated')
        >
        <span>Agregado</span>
    </label>

    <label>
        <input
            type="radio"
            name="fleet_relation"
            value="rented"
            @checked($fleetRelation === 'rented')
        >
        <span>Alugado</span>
    </label>

    <label>
        <input
            type="radio"
            name="fleet_relation"
            value="all"
            @checked($fleetRelation === 'all')
        >
        <span>Todos</span>
    </label>

</div>
</div>

<div class="fuel-history-filter fuel-history-filter-date fuel-history-filter-start">
<label>Data inicial</label>
<input type="date" name="start_date" value="{{ request('start_date') }}">
</div>

<div class="fuel-history-filter fuel-history-filter-date fuel-history-filter-end">
<label>Data final</label>
<input type="date" name="end_date" value="{{ request('end_date') }}">
</div>

<div class="fuel-history-filter fuel-history-filter-product">
<label>Produto</label>
<select name="fuel_product_id">
<option value="">Todos</option>
@foreach ($products as $product)
<option value="{{ $product->id }}" @selected(request('fuel_product_id') == $product->id)>{{ $product->name }}</option>
@endforeach
</select>
</div>

<div class="fuel-history-filter fuel-history-filter-tank">
<label>Tanque</label>
<select name="fuel_tank_id">
<option value="">Todos</option>
@foreach ($tanks as $tank)
<option value="{{ $tank->id }}" @selected(request('fuel_tank_id') == $tank->id)>{{ $tank->name }}</option>
@endforeach
</select>
</div>

<div class="fuel-history-filter fuel-history-filter-source">
<label>Origem</label>
<select name="source">
<option value="">Todas</option>
<option value="internal_tank" @selected(request('source') === 'internal_tank')>Tanque da unidade</option>
<option value="external_station" @selected(request('source') === 'external_station')>Posto externo</option>
</select>
</div>

<div class="fuel-history-filter fuel-history-filter-status">
<label>Status</label>
<select name="status">
<option value="">Todos</option>
<option value="active" @selected(request('status', 'active') === 'active')>Realizado</option>
<option value="cancelled" @selected(request('status', 'active') === 'cancelled')>Cancelado</option>
</select>
</div>

<div class="fuel-history-filter-actions">
<label>&nbsp;</label>
<div class="fuel-history-filter-buttons">
<button class="fuel-primary-action">Filtrar</button>
@if ($hasFilters)
<a href="{{ route('fuel.fillings.history') }}" class="fuel-secondary-action">Limpar</a>
@endif
</div>
</div>

</form>
<section class="fuel-panel">

<div class="fuel-history-panel-toolbar">

    <div class="fuel-history-result-count">
        <i class="bi bi-list-ul"></i>
        <span>
            Encontrados
            <strong>{{ number_format($filteredCount, 0, ',', '.') }}</strong>
            {{ $filteredCount === 1 ? 'registro de abastecimento' : 'registros de abastecimentos' }}
            neste período.
        </span>
    </div>

    @if(request()->filled('vehicle_id'))

        <a
            href="{{ route('fuel.tanks.index', [
                'fuel_modal' => 'filling',
                'fuel_vehicle_id' => request('vehicle_id'),
            ]) }}"
            class="fuel-secondary-action fuel-history-new-filling"
            title="Registrar novo abastecimento para o veículo filtrado"
        >
            <i class="bi bi-fuel-pump"></i>
            @php
                $filteredVehicle = $vehicles->firstWhere(
                    'id',
                    (int) request('vehicle_id')
                );

                $filteredVehicleLabel =
                    $filteredVehicle?->plate
                    ?: $filteredVehicle?->name
                    ?: 'veículo selecionado';
            @endphp

            <span>
                Novo abastecimento no veículo
                {{ $filteredVehicleLabel }}
            </span>
        </a>

    @endif

    @if($filteredCount <= 250)
        <a
            href="{{ route('fuel.fillings.history.pdf', array_merge(request()->query(), ['fleet_relation' => $fleetRelation])) }}"
            class="fuel-history-pdf-action"
            title="Gerar PDF com os filtros atuais"
        >
            <i class="bi bi-file-earmark-pdf"></i>
            <span>Gerar PDF</span>
        </a>
    @else
        <button
            type="button"
            class="fuel-history-pdf-action is-disabled"
            disabled
            title="O limite para geração de PDF é de até 250 registros"
        >
            <i class="bi bi-file-earmark-pdf"></i>
            <span>Gerar PDF</span>
        </button>
    @endif

</div>

@if($filteredCount > 250)
    <div class="fuel-history-pdf-limit">
        <i class="bi bi-info-circle"></i>
        <span>
            O limite para geração de PDF é de até
            <strong>250 registros de abastecimentos</strong>.
            Refine o período ou os filtros para gerar o relatório.
        </span>
    </div>
@endif

<div class="fuel-table-wrap"><table class="fuel-table fuel-history-table"><thead><tr><th>Data</th><th>Veículo</th><th>Origem / produto</th><th>Quantidade</th><th>KM / HR</th><th>Responsável</th><th>Status</th><th class="fuel-history-actions-head">Ações</th></tr></thead><tbody>
@forelse ($fillings as $filling)
<tr class="{{ $filling->cancelled_at ? 'is-cancelled' : '' }}">
<td class="fuel-history-date">
    <strong>{{ $filling->filled_at?->format('d/m/Y') }}</strong>
    <small>{{ $filling->filled_at?->format('H:i') }}</small>
</td>
<td class="fuel-history-vehicle">
    <strong>{{ $filling->vehicle?->name ?: '—' }}</strong>
    <small>{{ $filling->vehicle?->plate ?: ($filling->vehicle?->asset_code ?: 'Sem identificação') }}</small>
</td>
<td>
    {{ $filling->source_label }}
    <br>
    <small>{{ $filling->location_label }} · {{ $filling->product?->name }}</small>
</td>
<td>
    {{ number_format((float) $filling->quantity_liters, 3, ',', '.') }} L
    @if ($fuelPermissions['view_costs'])
        <small>R$ {{ number_format((float) $filling->total_cost, 2, ',', '.') }}</small>
    @endif
</td>
<td class="fuel-history-reading">
    @php
        $vehicleUsesKm =
            (bool) ($filling->vehicle?->km_control_enabled ?? false);

        $vehicleUsesHours =
            (bool) ($filling->vehicle?->hours_control_enabled ?? false);
    @endphp

    @if($vehicleUsesKm || $vehicleUsesHours)

        <div class="fuel-history-meter-list">

            @if($vehicleUsesKm)
                <div class="fuel-history-meter-reading">
                    <strong>
                        {{ $filling->vehicle_km !== null
                            ? rtrim(
                                rtrim(
                                    number_format(
                                        (float) $filling->vehicle_km,
                                        2,
                                        ',',
                                        '.'
                                    ),
                                    '0'
                                ),
                                ','
                            )
                            : '—' }}
                        @if($filling->vehicle_km !== null)
                            km
                        @endif
                    </strong>

                    <span class="fuel-history-meter-tag">
                        KM
                    </span>
                </div>
            @endif

            @if($vehicleUsesHours)
                <div class="fuel-history-meter-reading">
                    <strong>
                        {{ $filling->vehicle_hours !== null
                            ? rtrim(
                                rtrim(
                                    number_format(
                                        (float) $filling->vehicle_hours,
                                        2,
                                        ',',
                                        '.'
                                    ),
                                    '0'
                                ),
                                ','
                            )
                            : '—' }}
                        @if($filling->vehicle_hours !== null)
                            h
                        @endif
                    </strong>

                    <span class="fuel-history-meter-tag is-hours">
                        HR
                    </span>
                </div>
            @endif

        </div>

    @else
        <strong>—</strong>
    @endif
</td>
<td>{{ $filling->responsible?->name ?: '—' }}</td>
<td>
    <span class="fuel-history-status {{ $filling->cancelled_at ? 'is-cancelled' : 'is-complete' }}">
        {{ $filling->cancelled_at ? 'Cancelado' : 'Realizado' }}
    </span>

    @if ($filling->replaces_filling_id)
        <small class="fuel-history-replacement-note">
            <i class="bi bi-arrow-repeat"></i>
            Correção do #{{ $filling->replaces_filling_id }}
        </small>
    @endif

    @if ($filling->replaced_by_filling_id)
        <small class="fuel-history-replacement-note">
            <i class="bi bi-arrow-right"></i>
            Substituído pelo #{{ $filling->replaced_by_filling_id }}
        </small>
    @elseif ($filling->cancelled_at)
        <small>{{ $filling->cancel_reason }} · {{ $filling->canceller?->name }}</small>
    @endif
</td>
<td class="fuel-history-actions">
    @if (! $filling->cancelled_at && $fuelPermissions['cancel'])
        <button
            type="button"
            class="fuel-row-action"
            title="Editar abastecimento"
            x-on:click="editId = {{ $filling->id }}"
        >
            <i class="bi bi-pencil-square"></i>
            <span>Editar</span>
        </button>

        <button
            type="button"
            class="fuel-row-action is-danger"
            title="Cancelar abastecimento"
            x-on:click="cancelId = {{ $filling->id }}"
        >
            <i class="bi bi-x-circle"></i>
            <span>Cancelar</span>
        </button>
    @endif
</td>
</tr>
@empty
<tr><td colspan="8" class="fuel-table-empty">Nenhum abastecimento encontrado.</td></tr>
@endforelse
</tbody></table></div>{{ $fillings->links() }}</section>
@foreach ($fillings as $filling)
@if (! $filling->cancelled_at && $fuelPermissions['cancel'])
<div
    class="fuel-modal-overlay"
    :class="{ 'is-open': editId === {{ $filling->id }} }"
>
    <div
        class="fuel-modal-card wide"
        x-data="{ source: @js(old('source', $filling->resolved_source)) }"
    >
        <div class="fuel-modal-header">
            <div>
                <span class="fuel-kicker">Correção auditável</span>
                <h2>Editar abastecimento #{{ $filling->id }}</h2>
                <p>O lançamento original será cancelado e um novo será criado em substituição.</p>
            </div>

            <button
                type="button"
                class="fuel-modal-close"
                x-on:click="editId = null"
            >
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        @if ((int) session('edit_filling_id') === (int) $filling->id && $errors->any())
            <div class="fuel-form-error">
                @foreach ($errors->all() as $message)
                    <span>{{ $message }}</span>
                @endforeach
            </div>
        @endif

        <form
            method="POST"
            action="{{ route('fuel.fillings.replace', $filling) }}"
            class="fuel-form"
        >
            @csrf

            <input type="hidden" name="driver_id" value="{{ old('driver_id', $filling->driver_id) }}">
            <input type="hidden" name="km_reading_confirmed" value="0">
            <input type="hidden" name="hours_reading_confirmed" value="0">

            <div class="fuel-form-grid fuel-edit-filling-grid">

                <label class="fuel-span-8">
                    Veículo
                    <select name="vehicle_id" required>
                        @foreach ($vehicles as $vehicle)
                            <option
                                value="{{ $vehicle->id }}"
                                @selected((string) old('vehicle_id', $filling->vehicle_id) === (string) $vehicle->id)
                            >
                                {{ $vehicle->name }}
                                @if ($vehicle->plate)
                                    · {{ $vehicle->plate }}
                                @endif
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="fuel-span-4">
                    Data/hora
                    <input
                        type="datetime-local"
                        name="filled_at"
                        value="{{ old('filled_at', $filling->filled_at?->format('Y-m-d\TH:i')) }}"
                        required
                    >
                </label>

                <label class="fuel-span-4">
                    Origem
                    <select name="source" x-model="source" required>
                        <option value="internal_tank">Tanque da unidade</option>
                        <option value="external_station">Posto externo</option>
                    </select>
                </label>

                <label
                    class="fuel-span-8"
                    x-show="source === 'internal_tank'"
                >
                    Tanque/produto
                    <select
                        name="fuel_tank_id"
                        :required="source === 'internal_tank'"
                    >
                        <option value="">Selecione</option>
                        @foreach ($tanks as $tank)
                            <option
                                value="{{ $tank->id }}"
                                @selected((string) old('fuel_tank_id', $filling->fuel_tank_id) === (string) $tank->id)
                            >
                                {{ $tank->name }} · {{ $tank->product?->name }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label
                    class="fuel-span-4"
                    x-show="source === 'external_station'"
                >
                    Produto
                    <select
                        name="fuel_product_id"
                        :required="source === 'external_station'"
                    >
                        <option value="">Selecione</option>
                        @foreach ($products as $product)
                            <option
                                value="{{ $product->id }}"
                                @selected((string) old('fuel_product_id', $filling->fuel_product_id) === (string) $product->id)
                            >
                                {{ $product->name }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="fuel-span-4">
                    Litros
                    <input
                        type="number"
                        name="quantity_liters"
                        min="0.001"
                        step="0.001"
                        value="{{ old('quantity_liters', $filling->quantity_liters) }}"
                        required
                    >
                </label>

                <label class="fuel-span-4">
                    Hodômetro
                    <input
                        type="number"
                        name="vehicle_km"
                        min="0"
                        step="0.01"
                        value="{{ old('vehicle_km', $filling->vehicle_km) }}"
                    >
                </label>

                <label class="fuel-span-4">
                    Horímetro
                    <input
                        type="number"
                        name="vehicle_hours"
                        min="0"
                        step="0.01"
                        value="{{ old('vehicle_hours', $filling->vehicle_hours) }}"
                    >
                </label>

                <label
                    class="fuel-span-8"
                    x-show="source === 'external_station'"
                >
                    Fornecedor/posto
                    <input
                        type="text"
                        name="supplier_name"
                        maxlength="255"
                        value="{{ old('supplier_name', $filling->supplier_name) }}"
                    >
                </label>

                <label
                    class="fuel-span-6"
                    x-show="source === 'external_station'"
                >
                    CPF/CNPJ do fornecedor
                    <input
                        type="text"
                        name="supplier_document"
                        maxlength="20"
                        value="{{ old('supplier_document', $filling->supplier_document) }}"
                    >
                </label>

                <label
                    class="fuel-span-4"
                    x-show="source === 'external_station'"
                >
                    Documento/NF/cupom
                    <input
                        type="text"
                        name="document_number"
                        maxlength="255"
                        value="{{ old('document_number', $filling->document_number) }}"
                    >
                </label>

                <label
                    class="fuel-span-4"
                    x-show="source === 'external_station'"
                >
                    Custo unitário
                    <input
                        type="number"
                        name="unit_cost"
                        min="0"
                        step="0.0001"
                        value="{{ old('unit_cost', $filling->unit_cost) }}"
                    >
                </label>

                <label
                    class="fuel-span-4"
                    x-show="source === 'external_station'"
                >
                    Custo total
                    <input
                        type="number"
                        name="total_cost"
                        min="0"
                        step="0.01"
                        value="{{ old('total_cost', $filling->total_cost) }}"
                    >
                </label>

                <label class="fuel-span-12">
                    Observação
                    <textarea name="notes" rows="3">{{ old('notes', $filling->notes) }}</textarea>
                </label>

            </div>

            <div class="fuel-edit-warning">
                <i class="bi bi-shield-check"></i>
                <div>
                    <strong>O histórico original será preservado.</strong>
                    <span>
                        O abastecimento #{{ $filling->id }} será cancelado e vinculado
                        automaticamente ao novo lançamento.
                    </span>
                </div>
            </div>

            <div class="fuel-form-actions">
                <button
                    type="button"
                    class="fuel-secondary-action"
                    x-on:click="editId = null"
                >
                    Voltar
                </button>

                <button type="submit" class="fuel-primary-action">
                    <i class="bi bi-check2"></i>
                    Salvar correção
                </button>
            </div>
        </form>
    </div>
</div>
@endif
@endforeach

@foreach ($fillings as $filling)
@if (! $filling->cancelled_at && $fuelPermissions['cancel'])
<div class="fuel-modal-overlay" :class="{ 'is-open': cancelId === {{ $filling->id }} }"><div class="fuel-modal-card"><h2>Cancelar abastecimento #{{ $filling->id }}</h2><p>Informe o motivo. O lançamento será mantido no histórico para auditoria.</p><form method="POST" action="{{ route('fuel.fillings.cancel', $filling) }}" class="fuel-form">@csrf<textarea name="reason" required minlength="5" placeholder="Motivo do cancelamento">{{ old('reason') }}</textarea><div class="fuel-form-actions"><button type="button" class="fuel-secondary-action" x-on:click="cancelId = null">Voltar</button><button class="fuel-primary-action">Cancelar lançamento</button></div></form></div></div>
@endif
@endforeach


</main>
@endsection
