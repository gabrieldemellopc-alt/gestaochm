@extends('layouts.app')

@push('styles')
<link
    rel="stylesheet"
    href="{{ asset('css/pages/maintenance.css') }}"
>
@endpush

@section('content')

<div class="maintenance-index-page">

    <div class="maintenance-create-header">

        <div>

            <span class="maintenance-kicker">
                Manutenção
            </span>

            <h1>
                Manutenção do veículo {{ $vehicle->name }}
            </h1>

            <p>
                Escolha o procedimento que será executado neste veículo.
            </p>

        </div>

        <a
            href="{{ route('vehicles.index') }}"
            class="maintenance-back-button"
        >
            <i data-lucide="arrow-left"></i>

            Voltar para veículos
        </a>

    </div>

    <div class="maintenance-context-card">

        <div class="maintenance-vehicle-info">

            <div class="maintenance-vehicle-icon">
                <img
                    src="{{ asset('images/lixo.png') }}"
                    alt="Veículo"
                >
            </div>

            <div>

                <h2>
                    {{ $vehicle->name }}
                </h2>

                <div class="maintenance-meta">

                    <span>
                        {{ $vehicle->plate }}
                    </span>

                    <span>
                        •
                    </span>

                    <span>
                        {{ $vehicle->brand }}
                        {{ $vehicle->model }}
                    </span>

                    @if($vehicle->year)
                        <span>•</span>
                        <span>{{ $vehicle->year }}</span>
                    @endif

                </div>

                <span class="maintenance-status-badge">
                    {{ $vehicle->operational_status === 'maintenance' ? 'Em manutenção' : 'Operacional' }}
                </span>

            </div>

        </div>

        <div class="maintenance-context-grid">

            <div class="maintenance-context-item">
                <span>Hodômetro</span>

                <strong>
                    {{ number_format($vehicle->current_km ?? 0, 0, ',', '.') }}
                    km
                </strong>
            </div>

            <div class="maintenance-context-item">
                <span>Horímetro</span>

                <strong>
                    {{ number_format($vehicle->current_hours ?? 0, 0, ',', '.') }}
                    h
                </strong>
            </div>

            <div class="maintenance-context-item">
                <span>Divisão</span>

                <strong>
                    {{ $vehicle->division->name ?? '—' }}
                </strong>
            </div>

            <div class="maintenance-context-item">
                <span>Localidade</span>

                <strong>
                    {{ $vehicle->location->name ?? '—' }}
                </strong>
            </div>

        </div>

    </div>

    <div class="maintenance-workspace">

    {{-- ATALHOS --}}
    <aside class="maintenance-alert-shortcuts">

        <div class="maintenance-section-title">

            <div>

                <span>
                    Atalhos
                </span>

                <h2>
                    Manutenções em atenção
                </h2>

                <p>
                    Procedimentos próximos do vencimento ou já exigindo ação.
                </p>

            </div>

        </div>

        @if($alertProcedures->count())

            <div class="maintenance-alert-grid">

                @foreach($alertProcedures as $procedure)

                    <a
                        href="{{ route('vehicle.maintenance.create', [
                            'vehicle' => $vehicle->id,
                            'procedure_id' => $procedure->id,
                            'execution_type' => $procedure->can_be_internal ? 'internal' : 'external',
                        ]) }}"
                        class="maintenance-alert-card {{ $procedure->alert_status }}"
                    >

                        <div class="maintenance-alert-icon">

                            @if($procedure->alert_status === 'danger')
                                <i data-lucide="circle-alert"></i>
                            @else
                                <i data-lucide="triangle-alert"></i>
                            @endif

                        </div>

                        <div>

                            <strong>
                                {{ $procedure->name }}
                            </strong>

                            <span>
                                {{ $procedure->alert_reason }}
                            </span>

                        </div>

                        <i data-lucide="chevron-right"></i>

                    </a>

                @endforeach

            </div>

        @else

            <div class="maintenance-empty-shortcuts">

                <i data-lucide="check-circle"></i>

                <strong>
                    Nenhum alerta
                </strong>

                <p>
                    Este veículo não possui manutenções pendentes no momento.
                </p>

            </div>

        @endif

    </aside>

    {{-- PROCEDIMENTOS --}}
    <section class="maintenance-procedures-card">

        <div class="maintenance-section-title">

            <div>

                <span>
                    Procedimentos
                </span>

                <h2>
                    Escolha a manutenção
                </h2>

                <p>
                    Selecione o procedimento e o tipo de execução para avançar.
                </p>

            </div>

        </div>

        <div class="maintenance-procedure-grid">

            @forelse($procedures as $procedure)

                <div class="maintenance-procedure-card">

                    <div class="maintenance-procedure-header">

                        <div class="maintenance-procedure-icon">
                            <i data-lucide="wrench"></i>
                        </div>

                        <div>

                            <h3>
                                {{ $procedure->name }}
                            </h3>

                            <div class="maintenance-procedure-rules">

                                @if($procedure->validity_km)
                                    <span>
                                        <i data-lucide="gauge"></i>
                                        {{ number_format($procedure->interval_km, 0, ',', '.') }} km
                                    </span>
                                @endif

                                @if($procedure->validity_hours)
                                    <span>
                                        <i data-lucide="clock"></i>
                                        {{ number_format($procedure->interval_hours, 0, ',', '.') }} h
                                    </span>
                                @endif

                                @if($procedure->validity_period)
                                    <span>
                                        <i data-lucide="calendar-days"></i>
                                        {{ $procedure->interval_days }} dias
                                    </span>
                                @endif

                                @if(
                                    !$procedure->validity_km
                                    &&
                                    !$procedure->validity_hours
                                    &&
                                    !$procedure->validity_period
                                )
                                    <span>
                                        <i data-lucide="settings"></i>
                                        Manual
                                    </span>
                                @endif

                            </div>

                        </div>

                    </div>

                    <form
                        method="GET"
                        action="{{ route('vehicle.maintenance.create', $vehicle->id) }}"
                        class="maintenance-procedure-form"
                    >

                        <input
                            type="hidden"
                            name="procedure_id"
                            value="{{ $procedure->id }}"
                        >

                        <div class="form-group">

                            <label>
                                Execução
                            </label>

                            <select
                                name="execution_type"
                                class="form-input"
                                required
                            >

                                @if($procedure->can_be_internal)

                                    <option value="internal">
                                        Oficina interna
                                    </option>

                                @endif

                                <option value="external">
                                    Terceirizado
                                </option>

                            </select>

                        </div>

                        <button
                            type="submit"
                            class="chm-page-button primary full"
                        >
                            <span>
                                Avançar
                            </span>
                        
                            <i data-lucide="arrow-right"></i>
                        </button>

                    </form>

                </div>

            @empty

                <div class="maintenance-empty-fields">

                    <i data-lucide="info"></i>

                    <strong>
                        Nenhum procedimento vinculado
                    </strong>

                    <p>
                        Este veículo ainda não possui procedimentos configurados.
                    </p>

                </div>

            @endforelse

        </div>

    </section>

</div>

</div>

@endsection