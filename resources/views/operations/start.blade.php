@extends('layouts.app')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/pages/operations.css') }}?v=1">
@endpush

@section('content')

<div class="operations-page">

    <div class="operation-form-header">
        <div>
            <span class="operations-kicker">Iniciar Operação</span>

            <h1>{{ $vehicle->plate ?? 'Veículo' }}</h1>

            <p>
                Confirme os dados iniciais do veículo antes de iniciar a operação.
            </p>
        </div>

        <a href="{{ route('vehicles.details', $vehicle->id) }}" class="chm-page-button secondary">
            <i data-lucide="arrow-left"></i>
            Voltar ao veículo
        </a>
    </div>

    <form method="POST" action="{{ route('vehicles.operations.store', $vehicle->id) }}" class="operation-form-card">
        @csrf

        <div class="operation-form-grid">

            <div class="form-group">
                <label>KM inicial</label>
                <input
                    type="number"
                    step="0.01"
                    name="start_vehicle_km"
                    value="{{ old('start_vehicle_km') }}"
                    required
                >
                @error('start_vehicle_km')
                    <small>{{ $message }}</small>
                @enderror
            </div>

            <div class="form-group">
                <label>Horímetro inicial</label>
                <input
                    type="number"
                    step="0.01"
                    name="start_vehicle_hours"
                    value="{{ old('start_vehicle_hours') }}"
                >
                @error('start_vehicle_hours')
                    <small>{{ $message }}</small>
                @enderror
            </div>

            <div class="form-group">
                <label>Data/hora de início</label>
                <input
                    type="datetime-local"
                    name="start_datetime_reported"
                    value="{{ old('start_datetime_reported', now()->format('Y-m-d\TH:i')) }}"
                    required
                >
                @error('start_datetime_reported')
                    <small>{{ $message }}</small>
                @enderror
            </div>

        </div>

        <div class="form-group">
            <label>Observações</label>
            <textarea
                name="start_observation"
                rows="4"
                placeholder="Ex: saída para coleta, rota emergencial, veículo liberado pelo supervisor..."
            >{{ old('start_observation') }}</textarea>
        </div>

        <div class="operation-delay-box">
            <div>
                <strong>Lançamento fora do horário</strong>
                <p>
                    Se a data/hora informada tiver diferença superior a 15 minutos em relação ao horário atual do sistema, será obrigatório justificar.
                </p>
            </div>

            <div class="operation-form-grid">

                <div class="form-group">
                    <label>Motivo</label>
                    <select name="start_delay_reason">
                        <option value="">Selecione se necessário</option>

                        @foreach($delayReasons as $value => $label)
                            <option value="{{ $value }}" @selected(old('start_delay_reason') === $value)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>

                    @error('start_delay_reason')
                        <small>{{ $message }}</small>
                    @enderror
                </div>

                <div class="form-group">
                    <label>Justificativa</label>
                    <input
                        type="text"
                        name="start_delay_justification"
                        value="{{ old('start_delay_justification') }}"
                        placeholder="Descreva a autorização ou o motivo operacional"
                    >

                    @error('start_delay_justification')
                        <small>{{ $message }}</small>
                    @enderror
                </div>

            </div>
        </div>

        <div class="operation-form-footer">
            <a href="{{ route('vehicles.details', $vehicle->id) }}" class="chm-page-button secondary">
                Cancelar
            </a>

            <button type="submit" class="chm-page-button primary">
                <i data-lucide="play"></i>
                Iniciar operação
            </button>
        </div>

    </form>

</div>

@endsection