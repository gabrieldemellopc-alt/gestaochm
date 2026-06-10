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
        x-data="maintenanceSummary()"
    >



        @csrf



        <input

            type="hidden"

            name="procedure_id"

            value="{{ $procedure->id }}"

        >



        <input
            type="hidden"
            name="maintenance_type"
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

                                name="performed_km"

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

                                name="performed_hours"
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

                                name="extra_cost"
                                class="form-input"
                                x-model.number="extraCost"

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

                                name="notes"

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
                                                name="fields[{{ $field->slug }}]"
                                                class="form-input"
                                                value="{{ old('fields.' . $field->slug) }}"
                                                x-model="simpleFields['{{ $field->slug }}']"
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
                                                name="fields[{{ $field->slug }}]"
                                                class="form-input"
                                                value="{{ old('fields.' . $field->slug) }}"
                                                x-model="simpleFields['{{ $field->slug }}']"
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
                                                    name="fields[{{ $field->slug }}]"
                                                    class="form-input"
                                                    x-model="stockFields['{{ $field->slug }}'].itemId"
                                                    @change="updateStockField('{{ $field->slug }}')"
                                                    {{ $field->required ? 'required' : '' }}
                                                >
                                                    <option
                                                        value=""
                                                        data-name=""
                                                        data-unit=""
                                                        data-cost="0"
                                                    >
                                                        Selecione o item
                                                    </option>

                                                    @foreach(($field->stockCategory->items ?? []) as $stockItem)
                                                        <option
                                                            value="{{ $stockItem->id }}"
                                                            data-name="{{ $stockItem->name }}"
                                                            data-unit="{{ $stockItem->unit }}"
                                                            data-cost="{{ $stockItem->unit_cost ?? 0 }}"
                                                            @selected(old('fields.' . $field->slug) == $stockItem->id)
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

                                            <div class="form-group">

                                                <label>
                                                    Quantidade utilizada

                                                    @if($field->required)
                                                        <span class="required-mark">*</span>
                                                    @endif
                                                </label>

                                                <input
                                                    type="number"
                                                    step="1"
                                                    min="1"
                                                    name="fields[{{ $field->slug }}_quantity]"
                                                    class="form-input"
                                                    value="{{ old('fields.' . $field->slug . '_quantity', 1) }}"
                                                    x-model.number="stockFields['{{ $field->slug }}'].quantity"
                                                    {{ $field->required ? 'required' : '' }}
                                                >

                                                <small class="field-help">
                                                    Informe a quantidade consumida na manutenção.
                                                </small>

                                            </div>

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
                            <span>Veículo</span>
                            <strong>{{ $vehicle->name }}</strong>
                        </div>

                        <div class="maintenance-summary-item">
                            <span>Procedimento</span>
                            <strong>{{ $procedure->name }}</strong>
                        </div>

                        <div class="maintenance-summary-item">
                            <span>Execução</span>
                            <strong>{{ $executionType === 'internal' ? 'Interna' : 'Terceirizada' }}</strong>
                        </div>

                        <div class="maintenance-summary-divider">
                            Campos preenchidos
                        </div>

                        <template x-if="filledSimpleFields.length === 0 && filledStockFields.length === 0">
                            <div class="maintenance-summary-empty">
                                Nenhum campo adicional preenchido ainda.
                            </div>
                        </template>

                        <template x-for="field in filledSimpleFields" :key="field.slug">
                            <div class="maintenance-summary-item">
                                <span x-text="field.label"></span>
                                <strong x-text="field.value"></strong>
                            </div>
                        </template>

                        <template x-for="field in filledStockFields" :key="field.slug">
                            <div class="maintenance-summary-stock">
                                <div class="maintenance-summary-stock-top">
                                    <span x-text="field.label"></span>
                                    <strong x-text="field.name"></strong>
                                </div>

                                <div class="maintenance-summary-stock-details">
                                    <small>
                                        Qtd:
                                        <b x-text="formatNumber(field.quantity)"></b>
                                        <b x-text="field.unit"></b>
                                    </small>

                                    <small>
                                        Unit:
                                        <b x-text="formatMoney(field.unitCost)"></b>
                                    </small>
                                </div>

                                <div class="maintenance-summary-stock-total">
                                    <span>Subtotal</span>
                                    <strong x-text="formatMoney(field.subtotal)"></strong>
                                </div>
                            </div>
                        </template>

                        <div class="maintenance-summary-divider">
                            Custos
                        </div>

                        <div class="maintenance-summary-item">
                            <span>Custo adicional</span>
                            <strong x-text="formatMoney(extraCost || 0)"></strong>
                        </div>

                        <div class="maintenance-summary-total">
                            <span>Total estimado</span>
                            <strong x-text="formatMoney(totalEstimated)"></strong>
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

                            <i data-lucide="save"></i>



                            Salvar manutenção

                        </button>



                    </div>



                </div>



            </aside>



        </div>



    </form>



</div>

<script>
    function maintenanceSummary() {
        return {
            extraCost: {{ old('extra_cost', 0) ?: 0 }},

            simpleFields: {
                @foreach($procedure->fields as $field)
                    @if(in_array($field->field_type, ['text', 'number']))
                        '{{ $field->slug }}': @json(old('fields.' . $field->slug, '')),
                    @endif
                @endforeach
            },

            simpleFieldLabels: {
                @foreach($procedure->fields as $field)
                    @if(in_array($field->field_type, ['text', 'number']))
                        '{{ $field->slug }}': @json($field->label),
                    @endif
                @endforeach
            },

            stockFields: {
                @foreach($procedure->fields as $field)
                    @if($field->field_type === 'stock_item')
                        '{{ $field->slug }}': {
                            label: @json($field->label),
                            itemId: @json(old('fields.' . $field->slug, '')),
                            name: '',
                            unit: '',
                            unitCost: 0,
                            quantity: {{ old('fields.' . $field->slug . '_quantity', 1) ?: 1 }},
                        },
                    @endif
                @endforeach
            },

            init() {
                this.$nextTick(() => {
                    Object.keys(this.stockFields).forEach((slug) => {
                        this.updateStockField(slug);
                    });
                });
            },

            updateStockField(slug) {
                const select = document.querySelector(`[name="fields[${slug}]"]`);

                if (!select) {
                    return;
                }

                const option = select.options[select.selectedIndex];

                this.stockFields[slug].name = option?.dataset?.name || '';
                this.stockFields[slug].unit = option?.dataset?.unit || '';
                this.stockFields[slug].unitCost = parseFloat(option?.dataset?.cost || 0);
            },

            get filledSimpleFields() {
                return Object.keys(this.simpleFields)
                    .filter((slug) => {
                        return this.simpleFields[slug] !== null &&
                            this.simpleFields[slug] !== undefined &&
                            String(this.simpleFields[slug]).trim() !== '';
                    })
                    .map((slug) => {
                        return {
                            slug: slug,
                            label: this.simpleFieldLabels[slug],
                            value: this.simpleFields[slug],
                        };
                    });
            },

            get filledStockFields() {
                return Object.keys(this.stockFields)
                    .filter((slug) => {
                        return this.stockFields[slug].itemId &&
                            this.stockFields[slug].name;
                    })
                    .map((slug) => {
                        const field = this.stockFields[slug];
                        const quantity = parseFloat(field.quantity || 0);
                        const unitCost = parseFloat(field.unitCost || 0);

                        return {
                            slug: slug,
                            label: field.label,
                            name: field.name,
                            unit: field.unit,
                            quantity: quantity,
                            unitCost: unitCost,
                            subtotal: quantity * unitCost,
                        };
                    });
            },

            get stockTotal() {
                return this.filledStockFields.reduce((total, field) => {
                    return total + field.subtotal;
                }, 0);
            },

            get totalEstimated() {
                return this.stockTotal + parseFloat(this.extraCost || 0);
            },

            formatMoney(value) {
                return new Intl.NumberFormat('pt-BR', {
                    style: 'currency',
                    currency: 'BRL'
                }).format(parseFloat(value || 0));
            },

            formatNumber(value) {
                return new Intl.NumberFormat('pt-BR', {
                    minimumFractionDigits: 0,
                    maximumFractionDigits: 2
                }).format(parseFloat(value || 0));
            }
        }
    }
</script>

@endsection