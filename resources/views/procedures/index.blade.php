@extends('layouts.app')



@push('styles')

<link

    rel="stylesheet"

    href="{{ asset('css/pages/procedures.css') }}?v=3"
>

@endpush



@section('content')



<div class="procedures-page">



    {{-- HEADER --}}

    <div class="procedures-header">



        <div>

            <span class="procedures-kicker">

                Oficina / Procedimentos

            </span>



            <h1>

                Procedimentos

            </h1>



            <p>

                Configure regras operacionais, periodicidades e campos exigidos nas manutenções da frota.

            </p>

        </div>



        <div class="procedures-header-actions">



            <a

                href="{{ route('workshop.index') }}"

                class="chm-page-button secondary"

            >

                <i class="bi bi-arrow-left"></i>

                Voltar para oficina

            </a>



            <a

                href="{{ route('procedures.create') }}"

                class="chm-page-button primary"
                @unless($canCreateProcedures) style="display: none !important;" aria-hidden="true" @endunless
            >

                <i class="bi bi-plus-lg"></i>

                @if($canCreateProcedures)
                    Novo procedimento
                @endif
            </a>



        </div>



    </div>



    {{-- RESUMO --}}

    <div class="procedures-summary-grid">



        <div class="procedures-summary-card">

            <div class="procedures-summary-icon">

                <i class="bi bi-clipboard"></i>

            </div>



            <div>

                <span>Total</span>

                <strong>{{ $procedures->count() }}</strong>

                <p>Procedimentos cadastrados</p>

            </div>

        </div>



        <div class="procedures-summary-card">

            <div class="procedures-summary-icon">

                <i class="bi bi-speedometer2"></i>

            </div>



            <div>

                <span>Por KM</span>

                <strong>{{ $procedures->where('validity_km', true)->count() }}</strong>

                <p>Controlados por quilometragem</p>

            </div>

        </div>



        <div class="procedures-summary-card">

            <div class="procedures-summary-icon">

                <i class="bi bi-clock"></i>

            </div>



            <div>

                <span>Por horas</span>

                <strong>{{ $procedures->where('validity_hours', true)->count() }}</strong>

                <p>Controlados por horímetro</p>

            </div>

        </div>



        <div class="procedures-summary-card">

            <div class="procedures-summary-icon">

                <i class="bi bi-calendar-week"></i>

            </div>



            <div>

                <span>Por período</span>

                <strong>{{ $procedures->where('validity_period', true)->count() }}</strong>

                <p>Controlados por dias</p>

            </div>

        </div>



    </div>



    {{-- SECTION TITLE --}}

    <div class="procedures-section-header">

        <div>

            <span>Regras operacionais</span>

            <h2>Procedimentos cadastrados</h2>

        </div>



        <p>

            Cada procedimento define quando uma manutenção deve ser executada e quais informações precisam ser registradas.

        </p>

    </div>



    {{-- GRID --}}

    <div class="procedures-grid">



        @forelse($procedures as $procedure)



            <div class="procedure-card">



                <div class="procedure-card-header">



                    <div class="procedure-main">



                        <div

                            class="procedure-color"

                            style="background: {{ $procedure->color ?? '#22c55e' }};"

                        ></div>



                        <div class="procedure-title-block">



                            <h3>

                                {{ $procedure->name }}

                            </h3>



                            <div class="procedure-rules">



                                @if($procedure->validity_km)



                                    <span class="procedure-rule-badge km">

                                        <i class="bi bi-speedometer2"></i>



                                        @if($procedure->interval_km > 0)

                                            {{ number_format($procedure->interval_km, 0, ',', '.') }} km

                                        @else

                                            Por KM

                                        @endif

                                    </span>



                                @endif



                                @if($procedure->validity_hours)



                                    <span class="procedure-rule-badge hours">

                                        <i class="bi bi-clock"></i>



                                        @if($procedure->interval_hours > 0)

                                            {{ number_format($procedure->interval_hours, 0, ',', '.') }} h

                                        @else

                                            Por HR

                                        @endif

                                    </span>



                                @endif



                                @if($procedure->validity_period)



                                    <span class="procedure-rule-badge days">

                                        <i class="bi bi-calendar-week"></i>



                                        @if($procedure->interval_days > 0)

                                            {{ $procedure->interval_days }} dias

                                        @else

                                            Por período

                                        @endif

                                    </span>



                                @endif



                                @if(

                                    !$procedure->validity_km

                                    &&

                                    !$procedure->validity_hours

                                    &&

                                    !$procedure->validity_period

                                )



                                    <span class="procedure-rule-badge muted">

                                        <i class="bi bi-gear"></i>

                                        Manual

                                    </span>



                                @endif



                            </div>



                        </div>



                    </div>



                    <div class="procedure-icon">

                        <i class="bi bi-clipboard"></i>

                    </div>



                </div>



                <div class="procedure-fields-wrapper">



                    <div class="procedure-fields-header">

                        <span>Campos exigidos</span>



                        <strong>

                            {{ $procedure->fields->count() }}

                        </strong>

                    </div>



                    <div class="procedure-fields">



                        @forelse($procedure->fields as $field)



                            <div class="field-pill">



                                <i class="bi bi-tag"></i>



                                {{ $field->label }}



                            </div>



                        @empty



                            <div class="procedure-empty-fields">



                                <i class="bi bi-info-circle"></i>



                                Nenhum campo adicional



                            </div>



                        @endforelse



                    </div>



                </div>



                <div class="procedure-footer">



                    @if($canUpdateProcedures)
                        <button
                            type="button"
                            class="procedure-link-vehicles-btn"
                            data-procedure-name="{{ $procedure->name }}"
                            data-sync-url="{{ route(
                                'procedures.vehicles.sync',
                                $procedure
                            ) }}"
                            data-linked-vehicles="{{ $procedure
                                ->vehicles
                                ->pluck('id')
                                ->values()
                                ->toJson() }}"
                        >
                            <i class="bi bi-link-45deg"></i>
                            Vincular a veículos
                        </button>
                    @endif

                    <a

                        href="{{ route('procedures.edit', $procedure->id) }}"

                        class="procedure-edit-btn"
                        @unless($canUpdateProcedures) style="display: none !important;" aria-hidden="true" @endunless
                    >

                        <i class="bi bi-pencil"></i>



                        @if($canUpdateProcedures)
                            Editar procedimento
                        @endif
                    </a>



                </div>



            </div>



        @empty



            <div class="procedures-empty">



                <div class="procedures-empty-icon">

                    <i class="bi bi-clipboard-x"></i>

                </div>



                <strong>

                    Nenhum procedimento cadastrado

                </strong>



                <p>

                    Cadastre procedimentos para controlar manutenções, trocas, revisões e regras operacionais da frota.

                </p>



                <a

                    href="{{ route('procedures.create') }}"

                    class="chm-page-button primary"
                    @unless($canCreateProcedures) style="display: none !important;" aria-hidden="true" @endunless
                >

                    <i class="bi bi-plus-lg"></i>



                    @if($canCreateProcedures)
                        Criar primeiro procedimento
                    @endif
                </a>



            </div>



        @endforelse



    </div>



</div>




@if($canUpdateProcedures)

<div
    id="procedureVehiclesModal"
    class="procedure-vehicles-modal"
    aria-hidden="true"
>
    <div
        class="procedure-vehicles-modal-backdrop"
        data-procedure-vehicles-close
    ></div>

    <div
        class="procedure-vehicles-modal-card"
        role="dialog"
        aria-modal="true"
        aria-labelledby="procedureVehiclesModalTitle"
    >
        <div class="procedure-vehicles-modal-header">

            <div>
                <span>Vínculo em massa</span>

                <h2 id="procedureVehiclesModalTitle">
                    Vincular a veículos
                </h2>

                <p>
                    Procedimento:
                    <strong data-procedure-modal-name>—</strong>
                </p>
            </div>

            <button
                type="button"
                class="procedure-vehicles-modal-close"
                data-procedure-vehicles-close
                aria-label="Fechar"
            >
                <i class="bi bi-x-lg"></i>
            </button>

        </div>

        <form
            method="POST"
            id="procedureVehiclesForm"
            class="procedure-vehicles-form"
        >
            @csrf
            @method('PUT')

            <div class="procedure-vehicles-toolbar">

                <div class="procedure-vehicles-filter-block">
                    <span>Relação com a frota</span>

                    <div class="procedure-vehicles-filter-tabs">
                        <button
                            type="button"
                            class="is-active"
                            data-fleet-filter="all"
                        >
                            Todos
                        </button>

                        <button
                            type="button"
                            data-fleet-filter="internal"
                        >
                            Internos
                        </button>

                        <button
                            type="button"
                            data-fleet-filter="aggregated"
                        >
                            Agregados
                        </button>

                        <button
                            type="button"
                            data-fleet-filter="rented"
                        >
                            Alugados
                        </button>
                    </div>
                </div>

                <label class="procedure-vehicles-search">
                    <span>Buscar veículo</span>

                    <input
                        type="search"
                        placeholder="Nome ou placa..."
                        data-vehicle-search
                        autocomplete="off"
                    >
                </label>

            </div>

            <div class="procedure-vehicles-types">
                <span>Tipo de veículo</span>

                <div class="procedure-vehicles-type-buttons">

                    <button
                        type="button"
                        class="is-active"
                        data-type-filter="all"
                    >
                        Todos os tipos
                    </button>

                    @foreach($vehicleTypeOptions as $typeKey => $typeData)
                        @php
                            $typeCount =
                                $vehicles
                                    ->where('type', $typeKey)
                                    ->count();
                        @endphp

                        @if($typeCount > 0)
                            <button
                                type="button"
                                data-type-filter="{{ $typeKey }}"
                            >
                                {{ $typeData['label'] }}
                                <small>{{ $typeCount }}</small>
                            </button>
                        @endif
                    @endforeach

                </div>
            </div>

            <div class="procedure-vehicles-selection-bar">

                <div>
                    <strong data-visible-count>
                        {{ $vehicles->count() }}
                    </strong>
                    exibido(s)

                    <span>·</span>

                    <strong data-selected-count>0</strong>
                    selecionado(s)
                </div>

                <div>
                    <button
                        type="button"
                        data-select-visible
                    >
                        Selecionar todos exibidos
                    </button>

                    <button
                        type="button"
                        data-unselect-visible
                    >
                        Desmarcar exibidos
                    </button>
                </div>

            </div>

            <div class="procedure-vehicles-list">

                @foreach($vehicles as $vehicle)

                    @php
                        $typeLabel =
                            $vehicleTypeOptions[$vehicle->type]['label']
                            ?? ucfirst(
                                str_replace(
                                    '_',
                                    ' ',
                                    $vehicle->type ?? 'veículo'
                                )
                            );

                        $fleetLabel =
                            match($vehicle->fleet_relation) {
                                'internal' => 'Interno',
                                'aggregated' => 'Agregado',
                                'rented' => 'Alugado',
                                default => 'Não informado',
                            };
                    @endphp

                    <label
                        class="procedure-vehicle-option"
                        data-vehicle-row
                        data-vehicle-id="{{ $vehicle->id }}"
                        data-fleet="{{ $vehicle->fleet_relation }}"
                        data-type="{{ $vehicle->type }}"
                        data-search="{{ strtolower(
                            ($vehicle->name ?? '')
                            .' '
                            .($vehicle->plate ?? '')
                        ) }}"
                    >
                        <input
                            type="checkbox"
                            name="vehicle_ids[]"
                            value="{{ $vehicle->id }}"
                            data-vehicle-checkbox
                        >

                        <span class="procedure-vehicle-option-check">
                            <i class="bi bi-check-lg"></i>
                        </span>

                        <span class="procedure-vehicle-option-main">
                            <strong>
                                {{ $vehicle->name }}
                            </strong>

                            <small>
                                {{ $vehicle->plate ?: 'Sem placa' }}
                            </small>
                        </span>

                        <span class="procedure-vehicle-option-tags">
                            <em>{{ $typeLabel }}</em>
                            <em>{{ $fleetLabel }}</em>
                        </span>
                    </label>

                @endforeach

            </div>

            <div class="procedure-vehicles-modal-footer">

                <span>
                    <strong data-footer-selected-count>0</strong>
                    veículo(s) receberão este procedimento.
                </span>

                <div>
                    <button
                        type="button"
                        class="chm-page-button secondary"
                        data-procedure-vehicles-close
                    >
                        Cancelar
                    </button>

                    <button
                        type="submit"
                        class="chm-page-button primary"
                    >
                        <i class="bi bi-link-45deg"></i>
                        Salvar vínculos
                    </button>
                </div>

            </div>

        </form>
    </div>
</div>

@endif



<script>
(() => {
    const procedureVehiclesModalInit = true;
    const modal = document.getElementById('procedureVehiclesModal');

    if (!modal) {
        return;
    }

    const form =
        document.getElementById('procedureVehiclesForm');

    const modalName =
        modal.querySelector('[data-procedure-modal-name]');

    const searchInput =
        modal.querySelector('[data-vehicle-search]');

    const rows = Array.from(
        modal.querySelectorAll('[data-vehicle-row]')
    );

    const visibleCount =
        modal.querySelector('[data-visible-count]');

    const selectedCounts =
        modal.querySelectorAll(
            '[data-selected-count], [data-footer-selected-count]'
        );

    let fleetFilter = 'all';
    let typeFilter = 'all';

    const normalize = value =>
        String(value || '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLowerCase()
            .trim();

    const updateCounts = () => {
        const selected = rows.filter(row => {
            return row.querySelector(
                '[data-vehicle-checkbox]'
            )?.checked;
        }).length;

        const visible = rows.filter(
            row => !row.hidden
        ).length;

        selectedCounts.forEach(node => {
            node.textContent = selected;
        });

        if (visibleCount) {
            visibleCount.textContent = visible;
        }
    };

    const applyFilters = () => {
        const term = normalize(
            searchInput?.value
        );

        rows.forEach(row => {
            const matchesFleet =
                fleetFilter === 'all'
                || row.dataset.fleet === fleetFilter;

            const matchesType =
                typeFilter === 'all'
                || row.dataset.type === typeFilter;

            const matchesSearch =
                !term
                || normalize(
                    row.dataset.search
                ).includes(term);

            row.hidden = !(
                matchesFleet
                && matchesType
                && matchesSearch
            );
        });

        updateCounts();
    };

    const setActiveButton = (
        selector,
        activeButton
    ) => {
        modal.querySelectorAll(selector)
            .forEach(button => {
                button.classList.toggle(
                    'is-active',
                    button === activeButton
                );
            });
    };

    const resetFilters = () => {
        fleetFilter = 'all';
        typeFilter = 'all';

        if (searchInput) {
            searchInput.value = '';
        }

        const fleetAll =
            modal.querySelector(
                '[data-fleet-filter="all"]'
            );

        const typeAll =
            modal.querySelector(
                '[data-type-filter="all"]'
            );

        if (fleetAll) {
            setActiveButton(
                '[data-fleet-filter]',
                fleetAll
            );
        }

        if (typeAll) {
            setActiveButton(
                '[data-type-filter]',
                typeAll
            );
        }

        applyFilters();
    };

    const openModal = button => {
        let linkedIds = [];

        try {
            linkedIds = JSON.parse(
                button.dataset.linkedVehicles || '[]'
            ).map(Number);
        } catch (error) {
            linkedIds = [];
        }

        const linkedSet =
            new Set(linkedIds);

        rows.forEach(row => {
            const checkbox =
                row.querySelector(
                    '[data-vehicle-checkbox]'
                );

            if (checkbox) {
                checkbox.checked =
                    linkedSet.has(
                        Number(row.dataset.vehicleId)
                    );
            }
        });

        form.action =
            button.dataset.syncUrl || '';

        modalName.textContent =
            button.dataset.procedureName
            || 'Procedimento';

        resetFilters();
        updateCounts();

        modal.classList.add('is-open');
        modal.setAttribute(
            'aria-hidden',
            'false'
        );

        document.body.classList.add(
            'procedure-vehicles-modal-open'
        );
    };

    const closeModal = () => {
        modal.classList.remove('is-open');

        modal.setAttribute(
            'aria-hidden',
            'true'
        );

        document.body.classList.remove(
            'procedure-vehicles-modal-open'
        );
    };

    document
        .querySelectorAll(
            '.procedure-link-vehicles-btn'
        )
        .forEach(button => {
            button.addEventListener(
                'click',
                () => openModal(button)
            );
        });

    modal
        .querySelectorAll(
            '[data-procedure-vehicles-close]'
        )
        .forEach(button => {
            button.addEventListener(
                'click',
                closeModal
            );
        });

    modal
        .querySelectorAll('[data-fleet-filter]')
        .forEach(button => {
            button.addEventListener('click', () => {
                fleetFilter =
                    button.dataset.fleetFilter
                    || 'all';

                setActiveButton(
                    '[data-fleet-filter]',
                    button
                );

                applyFilters();
            });
        });

    modal
        .querySelectorAll('[data-type-filter]')
        .forEach(button => {
            button.addEventListener('click', () => {
                typeFilter =
                    button.dataset.typeFilter
                    || 'all';

                setActiveButton(
                    '[data-type-filter]',
                    button
                );

                applyFilters();
            });
        });

    searchInput?.addEventListener(
        'input',
        applyFilters
    );

    modal
        .querySelector('[data-select-visible]')
        ?.addEventListener('click', () => {
            rows
                .filter(row => !row.hidden)
                .forEach(row => {
                    const checkbox =
                        row.querySelector(
                            '[data-vehicle-checkbox]'
                        );

                    if (checkbox) {
                        checkbox.checked = true;
                    }
                });

            updateCounts();
        });

    modal
        .querySelector('[data-unselect-visible]')
        ?.addEventListener('click', () => {
            rows
                .filter(row => !row.hidden)
                .forEach(row => {
                    const checkbox =
                        row.querySelector(
                            '[data-vehicle-checkbox]'
                        );

                    if (checkbox) {
                        checkbox.checked = false;
                    }
                });

            updateCounts();
        });

    rows.forEach(row => {
        row.querySelector(
            '[data-vehicle-checkbox]'
        )?.addEventListener(
            'change',
            updateCounts
        );
    });

    document.addEventListener(
        'keydown',
        event => {
            if (
                event.key === 'Escape'
                && modal.classList.contains(
                    'is-open'
                )
            ) {
                closeModal();
            }
        }
    );
})();
</script>


@endsection
