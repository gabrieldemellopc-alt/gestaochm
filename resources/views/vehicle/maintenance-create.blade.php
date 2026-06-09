@extends('layouts.app')

@push('styles')
<link
    rel="stylesheet"
    href="{{ asset('css/pages/maintenance.css') }}?v=2"
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
                Lançar manutenção
            </h1>

            <p>
                Registre a execução do procedimento selecionado para este veículo.
            </p>

        </div>

        <a
            href="{{ route('vehicle.maintenance.index', $vehicle->id) }}"
            class="maintenance-back-button"
        >
            <i data-lucide="arrow-left"></i>

            Voltar para manutenções
        </a>

    </div>

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

            <div class="maintenance-context-item">

                <span>
                    Procedimento
                </span>

                <strong>
                    {{ $procedure->name }}
                </strong>

            </div>

            <div class="maintenance-context-item">

                <span>
                    Execução
                </span>

                <strong>
                    {{ $executionType === 'internal' ? 'Oficina interna' : 'Terceirizado' }}
                </strong>

            </div>

        </div>

    </div>

    <form
        method="POST"
        action="{{ route('vehicles.maintenance.store', $vehicle->id) }}"
        class="maintenance-form"
    >

        @csrf

        <input
            type="hidden"
            name="procedure_id"
            value="{{ $procedure->id }}"
        >

        <input
            type="hidden"
            name="execution_type"
            value="{{ $executionType }}"
        >

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
                                Informe os dados básicos da execução deste procedimento.
                            </p>

                        </div>

                        <i data-lucide="clipboard-wrench"></i>

                    </div>

                    <div class="maintenance-grid">

                        <div class="form-group">

                            <label>
                                Data da execução
                            </label>

                            <input
                                type="date"
                                name="performed_at"
                                class="form-input"
                                value="{{ old('performed_at', now()->format('Y-m-d')) }}"
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
                                Hodômetro no lançamento
                            </label>

                            <input
                                type="number"
                                step="1"
                                min="{{ $vehicle->current_km ?? 0 }}"
                                name="km"
                                class="form-input"
                                value="{{ old('km', $vehicle->current_km ?? 0) }}"
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
                                name="hours"
                                class="form-input"
                                value="{{ old('hours', $vehicle->current_hours ?? 0) }}"
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
                                name="additional_cost"
                                class="form-input"
                                value="{{ old('additional_cost', 0) }}"
                            >

                        </div>

                        <div class="form-group">

                            <label>
                                Status após lançamento
                            </label>

                            <select
                                name="vehicle_status_after"
                                class="form-input"
                                required
                            >

                                <option
                                    value="operational"
                                    @selected(old('vehicle_status_after', $vehicle->operational_status) === 'operational')
                                >
                                    Operacional
                                </option>

                                <option
                                    value="maintenance"
                                    @selected(old('vehicle_status_after', $vehicle->operational_status) === 'maintenance')
                                >
                                    Em manutenção
                                </option>

                            </select>

                        </div>

                        <div class="form-group full-width">

                            <label>
                                Observações
                            </label>

                            <textarea
                                name="observation"
                                rows="4"
                                class="form-input"
                                placeholder="Descreva detalhes da manutenção, diagnóstico, peças trocadas ou observações relevantes..."
                            >{{ old('observation') }}</textarea>

                        </div>

                    </div>

                </div>

                {{-- CAMPOS DINÂMICOS DO PROCEDIMENTO --}}
                <div class="maintenance-card">

                    <div class="maintenance-card-header">

                        <div>

                            <span>
                                Campos do procedimento
                            </span>

                            <h3>
                                {{ $procedure->name }}
                            </h3>

                            <p>
                                Preencha as informações complementares exigidas por este procedimento.
                            </p>

                        </div>

                        <i data-lucide="list-checks"></i>

                    </div>

                    @if($procedure->fields->count())

                        <div class="maintenance-fields">

                            @foreach($procedure->fields as $field)

                                <div class="maintenance-field-row">

                                    @if($field->field_type === 'text')

                                        <div class="form-group full-width">

                                            <label>
                                                {{ $field->label }}

                                                @if($field->required)
                                                    <span class="required-mark">*</span>
                                                @endif
                                            </label>

                                            <input
                                                type="text"
                                                name="dynamic_fields[{{ $field->slug }}]"
                                                class="form-input"
                                                value="{{ old('dynamic_fields.' . $field->slug) }}"
                                                {{ $field->required ? 'required' : '' }}
                                            >

                                        </div>

                                    @elseif($field->field_type === 'number')

                                        <div class="form-group full-width">

                                            <label>
                                                {{ $field->label }}

                                                @if($field->required)
                                                    <span class="required-mark">*</span>
                                                @endif
                                            </label>

                                            <input
                                                type="number"
                                                step="0.01"
                                                name="dynamic_fields[{{ $field->slug }}]"
                                                class="form-input"
                                                value="{{ old('dynamic_fields.' . $field->slug) }}"
                                                {{ $field->required ? 'required' : '' }}
                                            >

                                        </div>

                                    @elseif($field->field_type === 'stock_item')

                                        <div class="maintenance-stock-field">

                                            <div class="form-group">

                                                <label>
                                                    {{ $field->label }}

                                                    @if($field->required)
                                                        <span class="required-mark">*</span>
                                                    @endif
                                                </label>

                                                <select
                                                    name="stock_fields[{{ $field->slug }}][stock_item_id]"
                                                    class="form-input"
                                                    {{ $field->required ? 'required' : '' }}
                                                >

                                                    <option value="">
                                                        Selecione o item
                                                    </option>

                                                    @foreach(($field->stockCategory->items ?? []) as $stockItem)

                                                        <option
                                                            value="{{ $stockItem->id }}"
                                                            @selected(old('stock_fields.' . $field->slug . '.stock_item_id') == $stockItem->id)
                                                        >
                                                            {{ $stockItem->name }}
                                                            —
                                                            Estoque:
                                                            {{ number_format($stockItem->quantity, 2, ',', '.') }}
                                                            {{ $stockItem->unit }}
                                                        </option>

                                                    @endforeach

                                                </select>

                                                @if($field->stockCategory)

                                                    <small class="field-help">
                                                        Categoria:
                                                        {{ $field->stockCategory->name }}
                                                    </small>

                                                @endif

                                            </div>

                                            @if($field->has_quantity)

                                                <div class="form-group">

                                                    <label>
                                                        Quantidade utilizada

                                                        @if($field->required)
                                                            <span class="required-mark">*</span>
                                                        @endif
                                                    </label>

                                                    <input
                                                        type="number"
                                                        step="0.01"
                                                        min="0"
                                                        name="stock_fields[{{ $field->slug }}][quantity]"
                                                        class="form-input"
                                                        value="{{ old('stock_fields.' . $field->slug . '.quantity') }}"
                                                        {{ $field->required ? 'required' : '' }}
                                                    >

                                                </div>

                                            @endif

                                        </div>

                                    @endif

                                </div>

                            @endforeach

                        </div>

                    @else

                        <div class="maintenance-empty-fields">

                            <i data-lucide="info"></i>

                            <strong>
                                Nenhum campo adicional
                            </strong>

                            <p>
                                Este procedimento não possui campos complementares configurados.
                            </p>

                        </div>

                    @endif

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

                            <span>
                                Veículo
                            </span>

                            <strong>
                                {{ $vehicle->name }}
                            </strong>

                        </div>

                        <div class="maintenance-summary-item">

                            <span>
                                Procedimento
                            </span>

                            <strong>
                                {{ $procedure->name }}
                            </strong>

                        </div>

                        <div class="maintenance-summary-item">

                            <span>
                                Execução
                            </span>

                            <strong>
                                {{ $executionType === 'internal' ? 'Interna' : 'Terceirizada' }}
                            </strong>

                        </div>

                        <div class="maintenance-summary-item">

                            <span>
                                Campos adicionais
                            </span>

                            <strong>
                                {{ $procedure->fields->count() }}
                            </strong>

                        </div>

                        @if($procedure->validity_km)

                            <div class="maintenance-summary-item">

                                <span>
                                    Regra KM
                                </span>

                                <strong>
                                    {{ number_format($procedure->interval_km, 0, ',', '.') }}
                                    km
                                </strong>

                            </div>

                        @endif

                        @if($procedure->validity_hours)

                            <div class="maintenance-summary-item">

                                <span>
                                    Regra horas
                                </span>

                                <strong>
                                    {{ number_format($procedure->interval_hours, 0, ',', '.') }}
                                    h
                                </strong>

                            </div>

                        @endif

                        @if($procedure->validity_period)

                            <div class="maintenance-summary-item">

                                <span>
                                    Regra período
                                </span>

                                <strong>
                                    {{ $procedure->interval_days }}
                                    dias
                                </strong>

                            </div>

                        @endif

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
                            <i data-lucide="save"></i>

                            Salvar manutenção
                        </button>

                    </div>

                </div>

            </aside>

        </div>

    </form>

</div>

@endsection