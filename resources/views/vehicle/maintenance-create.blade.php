@extends('layouts.app')



@push('styles')

<link

    rel="stylesheet"

    href="{{ asset('css/pages/maintenance.css') }}?v=8"
>

@endpush



@section('content')



<div class="maintenance-create-page">



    {{-- HEADER --}}

    <div class="maintenance-create-header">



        <div>



            <span class="maintenance-kicker">

                Manutenção

            </span>



            <h1>

                Abrir manutenção

            </h1>



            <p>

                Abra uma manutenção para este veículo e acompanhe o andamento até a conclusão.


            </p>



        </div>



        <a

            href="{{ route('vehicle.maintenance.index', $vehicle->id) }}"

            class="maintenance-back-button"

        >

            <i class="bi bi-arrow-left"></i>



            Voltar para manutenções

        </a>



    </div>

    @if($errors->any())
        <div class="maintenance-form-errors" role="alert">
            <i class="bi bi-info-circle"></i>
            {{ $errors->first() }}
        </div>
    @endif



    {{-- CONTEXTO DO VEÍCULO --}}

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



                    @if($vehicle->brand || $vehicle->model)



                        <span>

                            •

                        </span>



                        <span>

                            {{ $vehicle->brand }}

                            {{ $vehicle->model }}

                        </span>



                    @endif



                    @if($vehicle->year)



                        <span>

                            •

                        </span>



                        <span>

                            {{ $vehicle->year }}

                        </span>



                    @endif



                </div>



                <span class="maintenance-status-badge">

                    {{ $vehicle->operational_status === 'maintenance' ? 'Em manutenção' : 'Operacional' }}

                </span>



            </div>



        </div>



        <div class="maintenance-context-grid">



            <div class="maintenance-context-item">



                <span>

                    Hodômetro

                </span>



                <strong>

                    {{ number_format($vehicle->current_km ?? 0, 0, ',', '.') }}

                    km

                </strong>



            </div>



            <div class="maintenance-context-item">



                <span>

                    Horímetro

                </span>



                <strong>

                    {{ number_format($vehicle->current_hours ?? 0, 0, ',', '.') }}

                    h

                </strong>



            </div>


        </div>



    </div>



    <form
        method="POST"
        action="{{ route('vehicles.maintenance.store', $vehicle->id) }}"
        class="maintenance-form"
        onsubmit="return confirmMaintenanceReadings(this);"
    >



        @csrf

        <input type="hidden" name="km_reading_confirmed" value="0">
        <input type="hidden" name="hours_reading_confirmed" value="0">


        <div class="maintenance-layout">



            {{-- COLUNA PRINCIPAL --}}

            <div class="maintenance-main">



                {{-- DADOS DA MANUTENÇÃO --}}

                <div class="maintenance-card">



                    <div class="maintenance-card-header">



                        <div>



                            <span>

                                Registro operacional

                            </span>



                            <h3>

                                Dados da manutenção

                            </h3>



                            <p>

                                Informe os dados básicos da entrada do veículo em manutenção.
                            </p>



                        </div>



                        <i class="bi bi-clipboard2-pulse"></i>



                    </div>



                    <div class="maintenance-grid">



                        <div class="form-group">



                            <label>

                                Data de entrada

                            </label>



                            <input
                                type="datetime-local"
                                name="started_at"
                                class="form-input"
                                value="{{ old('started_at', now()->format('Y-m-d\TH:i')) }}"
                                required
                            >



                        </div>



                        <div class="form-group">



                            <label>

                                Motivo

                            </label>



                            <select

                                name="reason"

                                class="form-input"

                                required

                            >



                                <option

                                    value="preventive"

                                    @selected(old('reason') === 'preventive')

                                >

                                    Preventiva

                                </option>



                                <option

                                    value="corrective"

                                    @selected(old('reason') === 'corrective')

                                >

                                    Corretiva

                                </option>



                                <option

                                    value="inspection"

                                    @selected(old('reason') === 'inspection')

                                >

                                    Inspeção

                                </option>



                                <option

                                    value="other"

                                    @selected(old('reason') === 'other')

                                >

                                    Outros

                                </option>



                            </select>



                        </div>

                        <div class="form-group">
                        
                            <label>
                                Categoria
                            </label>
                        
                            <select
                                name="maintenance_category"
                                class="form-input"
                                required
                            >
                                <option value="">
                                    Selecione...
                                </option>
                        
                                @foreach(
                                    \App\Services\MaintenanceService::maintenanceCategories()
                                    as $categoryKey => $categoryLabel
                                )
                                    <option
                                        value="{{ $categoryKey }}"
                                        @selected(
                                            old('maintenance_category') === $categoryKey
                                        )
                                    >
                                        {{ $categoryLabel }}
                                    </option>
                                @endforeach
                            </select>
                        
                        </div>

                        <div class="form-group">



                            <label>

                                Hodômetro no lançamento

                            </label>



                            <input

                                type="number"

                                step="1"

                                min="{{ $vehicle->current_km ?? 0 }}"

                                name="performed_km"
                                data-current-reading="{{ $vehicle->current_km ?? 0 }}"

                                class="form-input"

                                value="{{ old('performed_km', $vehicle->current_km ?? 0) }}"
                                required

                            >



                        </div>



                        <div class="form-group">



                            <label>

                                Horímetro no lançamento

                            </label>



                            <input

                                type="number"

                                step="1"

                                min="{{ $vehicle->current_hours ?? 0 }}"

                                name="performed_hours"
                                data-current-reading="{{ $vehicle->current_hours ?? 0 }}"
                                class="form-input"

                                value="{{ old('performed_hours', $vehicle->current_hours ?? 0) }}"
                                required

                            >



                        </div>



                        <div class="form-group">



                            <label>

                                Custo adicional

                            </label>



                            <input

                                type="number"

                                step="0.01"

                                min="0"

                                name="extra_cost"
                                class="form-input"

                                value="{{ old('extra_cost', 0) }}"      
                            >



                        </div>

                        <div class="form-group">
                            <label>
                                Status inicial
                            </label>
                        
                            <select
                                name="service_status"
                                class="form-input"
                                required
                            >
                                @foreach(\App\Services\MaintenanceService::serviceStatuses() as $statusKey => $statusLabel)
                                    <option
                                        value="{{ $statusKey }}"
                                        @selected(old('service_status', \App\Services\MaintenanceService::defaultServiceStatus()) === $statusKey)
                                    >
                                        {{ $statusLabel }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group maintenance-observations-field">


                            <label>

                                Observações

                            </label>



                            <textarea

                                name="notes"

                                rows="2"

                                class="form-input"

                                placeholder="Descreva detalhes da manutenção, diagnóstico, peças trocadas ou observações relevantes..."

                            >{{ old('notes') }}</textarea>



                        </div>



                    </div>



                </div>


            </div>



            {{-- SIDEBAR --}}

            <aside class="maintenance-side">



                <div class="maintenance-summary-card">



                    <div class="maintenance-summary-header">



                        <span>

                            Resumo

                        </span>



                        <h3>

                            Antes de salvar

                        </h3>



                    </div>



                    <div class="maintenance-summary-list">
                        <div class="maintenance-summary-item">
                            <span>Veículo</span>
                            <strong>{{ $vehicle->name }}</strong>
                        </div>
                        
                        <div class="maintenance-summary-item">
                            <span>Status inicial</span>
                            <strong>Será definido no formulário</strong>
                        </div>
                        
                        <div class="maintenance-summary-item">
                            <span>Custo inicial</span>
                            <strong>Informado como custo adicional da parada</strong>
                        </div>
                    </div>



                    <div class="maintenance-actions">



                        <a

                            href="{{ route('vehicle.maintenance.index', $vehicle->id) }}"

                            class="maintenance-cancel-btn"

                        >

                            Cancelar

                        </a>



                        <button

                            type="submit"

                            class="chm-page-button primary full"

                        >

                            <i class="bi bi-floppy"></i>



                            Abrir manutenção

                        </button>



                    </div>



                </div>



            </aside>



        </div>



    </form>



</div>

<script>
    function confirmMaintenanceReadings(form) {
        const checks = [
            ['performed_km', 'km_reading_confirmed', 500, 'KM'],
            ['performed_hours', 'hours_reading_confirmed', 24, 'horímetro'],
        ];

        for (const [field, confirmationField, threshold, label] of checks) {
            const input = form.querySelector(`[name="${field}"]`);
            const confirmation = form.querySelector(`[name="${confirmationField}"]`);
            confirmation.value = '0';

            if (! input || input.value === '') {
                continue;
            }

            const current = Number(input.dataset.currentReading || 0);
            const informed = Number(input.value);

            if (informed - current <= threshold) {
                continue;
            }

            if (! confirm(`O ${label} informado está muito acima da leitura atual. Confirma que a leitura está correta?`)) {
                input.focus();
                return false;
            }

            confirmation.value = '1';
        }

        return true;
    }
</script>

@endsection
