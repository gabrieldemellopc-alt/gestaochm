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
                <i data-lucide="arrow-left"></i>
                Voltar para oficina
            </a>

            <a
                href="{{ route('procedures.create') }}"
                class="chm-page-button primary"
            >
                <i data-lucide="plus"></i>
                Novo procedimento
            </a>

        </div>

    </div>

    {{-- RESUMO --}}
    <div class="procedures-summary-grid">

        <div class="procedures-summary-card">
            <div class="procedures-summary-icon">
                <i data-lucide="clipboard-list"></i>
            </div>

            <div>
                <span>Total</span>
                <strong>{{ $procedures->count() }}</strong>
                <p>Procedimentos cadastrados</p>
            </div>
        </div>

        <div class="procedures-summary-card">
            <div class="procedures-summary-icon">
                <i data-lucide="gauge"></i>
            </div>

            <div>
                <span>Por KM</span>
                <strong>{{ $procedures->where('validity_km', true)->count() }}</strong>
                <p>Controlados por quilometragem</p>
            </div>
        </div>

        <div class="procedures-summary-card">
            <div class="procedures-summary-icon">
                <i data-lucide="clock"></i>
            </div>

            <div>
                <span>Por horas</span>
                <strong>{{ $procedures->where('validity_hours', true)->count() }}</strong>
                <p>Controlados por horímetro</p>
            </div>
        </div>

        <div class="procedures-summary-card">
            <div class="procedures-summary-icon">
                <i data-lucide="calendar-days"></i>
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
                                        <i data-lucide="gauge"></i>

                                        @if($procedure->interval_km > 0)
                                            {{ number_format($procedure->interval_km, 0, ',', '.') }} km
                                        @else
                                            Por KM
                                        @endif
                                    </span>

                                @endif

                                @if($procedure->validity_hours)

                                    <span class="procedure-rule-badge hours">
                                        <i data-lucide="clock"></i>

                                        @if($procedure->interval_hours > 0)
                                            {{ number_format($procedure->interval_hours, 0, ',', '.') }} h
                                        @else
                                            Por HR
                                        @endif
                                    </span>

                                @endif

                                @if($procedure->validity_period)

                                    <span class="procedure-rule-badge days">
                                        <i data-lucide="calendar-days"></i>

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
                                        <i data-lucide="settings"></i>
                                        Manual
                                    </span>

                                @endif

                            </div>

                        </div>

                    </div>

                    <div class="procedure-icon">
                        <i data-lucide="clipboard-list"></i>
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

                                <i data-lucide="tag"></i>

                                {{ $field->label }}

                            </div>

                        @empty

                            <div class="procedure-empty-fields">

                                <i data-lucide="info"></i>

                                Nenhum campo adicional

                            </div>

                        @endforelse

                    </div>

                </div>

                <div class="procedure-footer">

                    <a
                        href="{{ route('procedures.edit', $procedure->id) }}"
                        class="procedure-edit-btn"
                    >
                        <i data-lucide="pencil"></i>

                        Editar procedimento
                    </a>

                </div>

            </div>

        @empty

            <div class="procedures-empty">

                <div class="procedures-empty-icon">
                    <i data-lucide="clipboard-x"></i>
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
                >
                    <i data-lucide="plus"></i>

                    Criar primeiro procedimento
                </a>

            </div>

        @endforelse

    </div>

</div>

@endsection