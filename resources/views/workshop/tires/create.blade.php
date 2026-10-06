@extends('layouts.app')

@php
    $pageTitle = 'Oficina';
    $pageSubtitle = 'Entrada de pneus';
@endphp

@push('styles')
<link
    rel="stylesheet"
    href="{{ asset('css/pages/workshop-tires.css') }}?v=5"
>

<style>
    .tire-entry-page {
        max-width: 1500px;
        margin: 0 auto;
    }

    .tire-entry-back {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        margin-bottom: 16px;
        color: var(--tires-muted);
        font-size: 12px;
        font-weight: 800;
        text-decoration: none;
    }

    .tire-entry-back:hover {
        color: var(--tires-text);
    }

    .tire-entry-tabs {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 8px;
        margin: 18px;
        padding: 5px;
        border: 1px solid var(--tires-border);
        border-radius: 9px;
        background: var(--tires-muted-surface);
    }

    .tire-entry-tab {
        min-height: 44px;
        border: 1px solid transparent;
        border-radius: 7px;
        background: transparent;
        color: var(--tires-muted);
        font-size: 12px;
        font-weight: 850;
        cursor: pointer;
    }

    .tire-entry-tab.is-active {
        color: var(--tires-text);
        border-color: var(--tires-border-strong);
        background: var(--tires-surface);
    }

    .tire-entry-mode-intro {
        margin: 0 18px 14px;
        padding: 14px 16px;
        border: 1px solid var(--tires-border);
        border-radius: 8px;
        background: var(--tires-muted-surface);
    }

    .tire-entry-mode-intro strong {
        display: block;
        color: var(--tires-text);
        font-size: 14px;
    }

    .tire-entry-mode-intro span {
        display: block;
        margin-top: 4px;
        color: var(--tires-muted);
        font-size: 12px;
        line-height: 1.5;
    }

    .tire-entry-individual-preview {
        margin: 0 18px 18px;
        padding: 24px;
        border: 1px dashed var(--tires-border-strong);
        border-radius: 8px;
        background: var(--tires-surface);
        text-align: center;
    }

    .tire-entry-individual-preview i {
        display: block;
        margin-bottom: 10px;
        color: #f1a55b;
        font-size: 28px;
    }

    .tire-entry-individual-preview strong {
        display: block;
        color: var(--tires-text);
        font-size: 16px;
    }

    .tire-entry-individual-preview p {
        max-width: 650px;
        margin: 7px auto 0;
        color: var(--tires-muted);
        font-size: 12px;
        line-height: 1.55;
    }

    @media (max-width: 700px) {
        .tire-entry-tabs {
            grid-template-columns: 1fr;
        }
    }
</style>
@endpush


@section('content')

@php
    $tirePermissions = array_merge([
        'view' => false,
        'create_entry' => false,
        'install' => false,
        'remove' => false,
        'measure' => false,
        'retread' => false,
        'cancel' => false,
        'view_costs' => false,
        'manage_inventory' => false,
    ], $tirePermissions ?? []);

    $canViewTireCosts =
        (bool) $tirePermissions['view_costs'];
@endphp


<div
    class="workshop-tires-page tire-entry-page"
    x-data="{
        activeTab: '{{ old('entry_mode') === 'individual' ? 'individual' : 'batch' }}'
    }"
>

    <a
        href="{{ route('workshop.tires.index') }}"
        class="tire-entry-back"
    >
        <i class="bi bi-arrow-left"></i>
        Voltar para controle de pneus
    </a>


    <div class="workshop-hero">

        <div>
            <span>
                Oficina / Pneus
            </span>

            <h1>
                Entrada de pneus
            </h1>

            <p>
                Registre pneus novos, usados ou adquiridos
                individualmente para o estoque.
            </p>
        </div>

    </div>


    @if($errors->any())
        <div class="alert alert-danger">
            <strong>
                Verifique os dados informados.
            </strong>

            <ul>
                @foreach($errors->all() as $error)
                    <li>
                        {{ $error }}
                    </li>
                @endforeach
            </ul>
        </div>
    @endif


    <section class="workshop-card">

        <div class="workshop-card-header">

            <div>
                <h2>
                    Tipo de entrada
                </h2>

                <p>
                    Escolha o fluxo adequado para o recebimento.
                </p>
            </div>

            <i class="bi bi-box-seam"></i>

        </div>


        <div class="tire-entry-tabs">

            <button
                type="button"
                class="tire-entry-tab"
                :class="{ 'is-active': activeTab === 'batch' }"
                @click="activeTab = 'batch'"
            >
                <i class="bi bi-boxes"></i>
                Em lote
            </button>

            <button
                type="button"
                class="tire-entry-tab"
                :class="{ 'is-active': activeTab === 'individual' }"
                @click="activeTab = 'individual'"
            >
                <i class="bi bi-circle"></i>
                Individual
            </button>

        </div>


        <div
            x-show="activeTab === 'batch'"
            x-cloak
        >

            <div class="tire-entry-mode-intro">
                <strong>
                    Entrada em lote
                </strong>

                <span>
                    Para vários pneus com as mesmas
                    características e mesma referência inicial
                    de sulco.
                </span>
            </div>

<form

                method="POST"

                action="{{ route('workshop.tires.entries.store') }}"

                class="workshop-entry-form"

            >



                @csrf



                <div class="workshop-form-grid">



                    <div class="form-group">

                        <label>Data da entrada</label>

                        <input

                            type="date"

                            name="entry_date"

                            value="{{ old('entry_date', now()->format('Y-m-d')) }}"

                            required

                        >

                    </div>



                    <div class="form-group">

                        <label>Quantidade</label>

                        <input

                            type="number"

                            name="quantity"

                            value="{{ old('quantity', 1) }}"

                            min="1"

                            required

                        >

                    </div>



                    <div class="form-group">

                        <label>Prefixo do código</label>

                        <input

                            type="text"

                            name="code_prefix"

                            value="{{ old('code_prefix', 'PN') }}"

                            placeholder="Ex: PN, AKSA-PN"

                            required

                        >

                    </div>



                    <div class="form-group">

                        <label>Marca</label>

                        <input

                            type="text"

                            name="brand"

                            value="{{ old('brand') }}"

                            placeholder="Ex: Michelin"

                        >

                    </div>



                    <div class="form-group">

                        <label>Modelo/Banda</label>

                        <input

                            type="text"

                            name="model"

                            value="{{ old('model') }}"

                            placeholder="Ex: X Multi"

                        >

                    </div>



                    <div class="form-group">

                        <label>Medida</label>

                        <input

                            type="text"

                            name="size"

                            value="{{ old('size') }}"

                            placeholder="Ex: 275/80 R22.5"

                        >

                    </div>



                    <div class="form-group">

                        <label>

                            Sulco inicial

                        </label>



                        <div class="input-with-suffix">

                            <input

                                type="number"

                                step="0.01"

                                name="initial_tread_depth"

                                value="{{ old('initial_tread_depth') }}"

                                placeholder="Ex: 15"

                            >



                            <span>

                                mm

                            </span>

                        </div>

                    </div>



                    <div class="form-group">
                        <label>
                            Quantidade de sulcos
                        </label>

                        <select
                            name="tread_grooves_count"
                            required
                        >
                            <option value="">Selecione</option>

                            <option
                                value="3"
                                @selected(old('tread_grooves_count') == 3)
                            >
                                3 sulcos
                            </option>

                            <option
                                value="4"
                                @selected(old('tread_grooves_count') == 4)
                            >
                                4 sulcos
                            </option>
                        </select>

                        <small class="form-help">
                            Informe quantos sulcos serão medidos neste pneu.
                        </small>
                    </div>


                    <div class="form-group">

                        <label>

                            Alerta de atenção

                        </label>



                        <div class="input-with-suffix">

                            <input

                                type="number"

                                step="0.01"

                                name="warning_tread_depth"

                                value="{{ old('warning_tread_depth', 5) }}"

                                placeholder="Ex: 5.00"

                            >



                            <span>

                                mm

                            </span>

                        </div>



                        <small class="form-help">

                            Valor sugerido: 5 mm. Abaixo ou igual a este sulco, o pneu entra em atenção.

                        </small>

                    </div>



                    <div class="form-group">

                        <label>

                            Alerta crítico

                        </label>



                        <div class="input-with-suffix">

                            <input

                                type="number"

                                step="0.01"

                                name="critical_tread_depth"

                                value="{{ old('critical_tread_depth', 3) }}"

                                placeholder="Ex: 3.00"

                            >



                            <span>

                                mm

                            </span>

                        </div>



                        <small class="form-help">

                            Valor sugerido: 3 mm. Abaixo ou igual a este sulco, o pneu fica crítico.

                        </small>

                    </div>



                    @if($canViewTireCosts)
<div class="form-group">

                        <label>Valor unitário</label>

                        <input

                            type="number"

                            step="0.01"

                            name="unit_cost"

                            value="{{ old('unit_cost') }}"

                            placeholder="Ex: 1200.00"

                        >

                    </div>
@endif



                    <div class="form-group">

                        <label>Fornecedor</label>

                        <x-supplier-autocomplete value="{{ old('supplier_name') }}" document-name="supplier_document" document-value="{{ old('supplier_document') }}" placeholder="Ex.: Pneus Bahia" />

                    </div>



                    <div class="form-group">

                        <label>Nota fiscal</label>

                        <input

                            type="text"

                            name="invoice_number"

                            value="{{ old('invoice_number') }}"

                            placeholder="Ex: NF 12345"

                        >

                    </div>



                </div>



                <div class="form-group">

                    <label>Observações</label>

                    <textarea

                        name="notes"

                        rows="3"

                        placeholder="Informações adicionais sobre a entrada..."

                    >{{ old('notes') }}</textarea>

                </div>



                <button

                    type="submit"

                    class="workshop-submit-btn"

                >

                    <i class="bi bi-floppy"></i>

                    Registrar entrada

                </button>



            </form>

        </div>


        <div
            x-show="activeTab === 'individual'"
            x-cloak
        >

            <div class="tire-entry-mode-intro">
                <strong>
                    Entrada individual
                </strong>

                <span>
                    Para pneus usados, recapados adquiridos ou
                    unidades que precisem registrar a condição
                    real de cada sulco na entrada.
                </span>
            </div>

            <form
                method="POST"
                action="{{ route('workshop.tires.entries.individual.store') }}"
                class="workshop-entry-form"
                x-data="{
                    condition: '{{ old('acquisition_condition', 'new') }}',
                    grooves: Number('{{ old('tread_grooves_count', 3) }}'),
                    tread1: '{{ old('tread_1') }}',
                    tread2: '{{ old('tread_2') }}',
                    tread3: '{{ old('tread_3') }}',
                    tread4: '{{ old('tread_4') }}',

                    get isDetailed() {
                        return this.condition !== 'new';
                    },

                    numberValue(value) {
                        if (
                            value === null
                            || value === undefined
                            || value === ''
                        ) {
                            return null;
                        }

                        const parsed = Number(value);

                        return Number.isFinite(parsed)
                            ? parsed
                            : null;
                    },

                    get readings() {
                        const values = [
                            this.numberValue(this.tread1),
                            this.numberValue(this.tread2),
                            this.numberValue(this.tread3),
                        ];

                        if (this.grooves === 4) {
                            values.push(
                                this.numberValue(this.tread4)
                            );
                        }

                        return values.filter(
                            value => value !== null
                        );
                    },

                    get completeReadings() {
                        return (
                            this.readings.length
                            === this.grooves
                        );
                    },

                    get minimumTread() {
                        if (! this.completeReadings) {
                            return null;
                        }

                        return Math.min(
                            ...this.readings
                        );
                    },

                    get averageTread() {
                        if (! this.completeReadings) {
                            return null;
                        }

                        const total =
                            this.readings.reduce(
                                (sum, value) => sum + value,
                                0
                            );

                        return total / this.readings.length;
                    },

                    formatMm(value) {
                        if (value === null) {
                            return '—';
                        }

                        return Number(value)
                            .toFixed(2)
                            .replace('.', ',')
                            + ' mm';
                    }
                }"
            >
                @csrf

                <input
                    type="hidden"
                    name="entry_mode"
                    value="individual"
                >


                <div class="tire-entry-individual-section">

                    <div class="tire-entry-individual-heading">
                        <div>
                            <strong>
                                Condição de aquisição
                            </strong>

                            <span>
                                Informe como este pneu está entrando
                                no estoque.
                            </span>
                        </div>
                    </div>


                    <div class="tire-entry-condition-grid">

                        <label
                            class="tire-entry-condition-option"
                            :class="{ 'is-active': condition === 'new' }"
                        >
                            <input
                                type="radio"
                                name="acquisition_condition"
                                value="new"
                                x-model="condition"
                            >

                            <span class="tire-entry-condition-icon">
                                <i class="bi bi-stars"></i>
                            </span>

                            <strong>
                                Novo
                            </strong>

                            <small>
                                Pneu sem uso anterior.
                            </small>
                        </label>


                        <label
                            class="tire-entry-condition-option"
                            :class="{ 'is-active': condition === 'used' }"
                        >
                            <input
                                type="radio"
                                name="acquisition_condition"
                                value="used"
                                x-model="condition"
                            >

                            <span class="tire-entry-condition-icon">
                                <i class="bi bi-arrow-repeat"></i>
                            </span>

                            <strong>
                                Usado
                            </strong>

                            <small>
                                Registrar a condição real dos sulcos.
                            </small>
                        </label>


                        <label
                            class="tire-entry-condition-option"
                            :class="{ 'is-active': condition === 'retread_acquired' }"
                        >
                            <input
                                type="radio"
                                name="acquisition_condition"
                                value="retread_acquired"
                                x-model="condition"
                            >

                            <span class="tire-entry-condition-icon">
                                <i class="bi bi-tools"></i>
                            </span>

                            <strong>
                                Recapado adquirido
                            </strong>

                            <small>
                                Pneu já adquirido após recapagem.
                            </small>
                        </label>

                    </div>

                </div>


                <div class="tire-entry-individual-section">

                    <div class="tire-entry-individual-heading">
                        <div>
                            <strong>
                                Identificação
                            </strong>

                            <span>
                                Dados principais do pneu recebido.
                            </span>
                        </div>
                    </div>


                    <div class="workshop-form-grid">

                        <div class="form-group">
                            <label>
                                Data da entrada
                            </label>

                            <input
                                type="date"
                                name="entry_date"
                                value="{{ old('entry_date', now()->format('Y-m-d')) }}"
                                required
                            >
                        </div>


                        <div class="form-group">
                            <label>
                                Prefixo do código
                            </label>

                            <input
                                type="text"
                                name="code_prefix"
                                value="{{ old('code_prefix', 'PN') }}"
                                placeholder="Ex: PN"
                                required
                            >
                        </div>


                        <div class="form-group">
                            <label>
                                Marca
                            </label>

                            <input
                                type="text"
                                name="brand"
                                value="{{ old('brand') }}"
                                placeholder="Ex: Michelin"
                            >
                        </div>


                        <div class="form-group">
                            <label>
                                Modelo/Banda
                            </label>

                            <input
                                type="text"
                                name="model"
                                value="{{ old('model') }}"
                                placeholder="Ex: X Multi"
                            >
                        </div>


                        <div class="form-group">
                            <label>
                                Medida
                            </label>

                            <input
                                type="text"
                                name="size"
                                value="{{ old('size') }}"
                                placeholder="Ex: 275/80 R22.5"
                            >
                        </div>


                        <div class="form-group">
                            <label>
                                Quantidade de sulcos
                            </label>

                            <select
                                name="tread_grooves_count"
                                x-model.number="grooves"
                                required
                            >
                                <option value="3">
                                    3 sulcos
                                </option>

                                <option value="4">
                                    4 sulcos
                                </option>
                            </select>
                        </div>

                    </div>

                </div>


                <div class="tire-entry-individual-section">

                    <div class="tire-entry-individual-heading">
                        <div>
                            <strong>
                                Sulcagem na entrada
                            </strong>

                            <span
                                x-text="
                                    isDetailed
                                        ? 'Registre a leitura real de cada sulco.'
                                        : 'Informe a profundidade inicial do pneu novo.'
                                "
                            ></span>
                        </div>
                    </div>


                    <div
                        x-show="! isDetailed"
                        x-cloak
                        class="workshop-form-grid"
                    >
                        <div class="form-group">
                            <label>
                                Sulco inicial
                            </label>

                            <div class="input-with-suffix">
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    max="50"
                                    name="initial_tread_depth"
                                    value="{{ old('initial_tread_depth') }}"
                                    placeholder="Ex: 15"
                                    :required="! isDetailed"
                                >

                                <span>
                                    mm
                                </span>
                            </div>

                            <small class="form-help">
                                Referência inicial uniforme do pneu novo.
                            </small>
                        </div>
                    </div>


                    <div
                        x-show="isDetailed"
                        x-cloak
                    >
                        <div class="tire-entry-groove-grid">

                            <div class="form-group">
                                <label>
                                    S1 · Lado externo
                                </label>

                                <div class="input-with-suffix">
                                    <input
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        max="50"
                                        name="tread_1"
                                        x-model="tread1"
                                        :required="isDetailed"
                                        placeholder="Ex: 9.00"
                                    >

                                    <span>mm</span>
                                </div>
                            </div>


                            <div class="form-group">
                                <label
                                    x-text="
                                        grooves === 4
                                            ? 'S2 · Centro externo'
                                            : 'S2 · Centro'
                                    "
                                ></label>

                                <div class="input-with-suffix">
                                    <input
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        max="50"
                                        name="tread_2"
                                        x-model="tread2"
                                        :required="isDetailed"
                                        placeholder="Ex: 8.00"
                                    >

                                    <span>mm</span>
                                </div>
                            </div>


                            <div class="form-group">
                                <label
                                    x-text="
                                        grooves === 4
                                            ? 'S3 · Centro interno'
                                            : 'S3 · Lado interno'
                                    "
                                ></label>

                                <div class="input-with-suffix">
                                    <input
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        max="50"
                                        name="tread_3"
                                        x-model="tread3"
                                        :required="isDetailed"
                                        placeholder="Ex: 7.00"
                                    >

                                    <span>mm</span>
                                </div>
                            </div>


                            <div
                                class="form-group"
                                x-show="grooves === 4"
                                x-cloak
                            >
                                <label>
                                    S4 · Lado interno
                                </label>

                                <div class="input-with-suffix">
                                    <input
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        max="50"
                                        name="tread_4"
                                        x-model="tread4"
                                        :required="isDetailed && grooves === 4"
                                        placeholder="Ex: 6.50"
                                    >

                                    <span>mm</span>
                                </div>
                            </div>

                        </div>


                        <div class="tire-entry-reading-summary">

                            <div>
                                <small>
                                    Menor sulco
                                </small>

                                <strong
                                    x-text="formatMm(minimumTread)"
                                ></strong>
                            </div>


                            <div>
                                <small>
                                    Média
                                </small>

                                <strong
                                    x-text="formatMm(averageTread)"
                                ></strong>
                            </div>

                        </div>
                    </div>

                </div>


                <div class="tire-entry-individual-section">

                    <div class="tire-entry-individual-heading">
                        <div>
                            <strong>
                                Limites operacionais
                            </strong>

                            <span>
                                Valores utilizados pelos alertas do CHM.
                            </span>
                        </div>
                    </div>


                    <div class="workshop-form-grid">

                        <div class="form-group">
                            <label>
                                Alerta de atenção
                            </label>

                            <div class="input-with-suffix">
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    max="50"
                                    name="warning_tread_depth"
                                    value="{{ old('warning_tread_depth', 5) }}"
                                >

                                <span>mm</span>
                            </div>
                        </div>


                        <div class="form-group">
                            <label>
                                Alerta crítico
                            </label>

                            <div class="input-with-suffix">
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    max="50"
                                    name="critical_tread_depth"
                                    value="{{ old('critical_tread_depth', 3) }}"
                                >

                                <span>mm</span>
                            </div>
                        </div>

                    </div>

                </div>


                <div class="tire-entry-individual-section">

                    <div class="tire-entry-individual-heading">
                        <div>
                            <strong>
                                Origem da entrada
                            </strong>

                            <span>
                                Fornecedor, documento e observações.
                            </span>
                        </div>
                    </div>


                    <div class="workshop-form-grid">

                        @if($canViewTireCosts)
                            <div class="form-group">
                                <label>
                                    Valor
                                </label>

                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    name="unit_cost"
                                    value="{{ old('unit_cost') }}"
                                    placeholder="Ex: 800.00"
                                >
                            </div>
                        @endif


                        <div class="form-group">
                            <label>
                                Fornecedor
                            </label>

                            <x-supplier-autocomplete
                                value="{{ old('supplier_name') }}"
                                document-name="supplier_document"
                                document-value="{{ old('supplier_document') }}"
                                placeholder="Ex.: Pneus Bahia"
                            />
                        </div>


                        <div class="form-group">
                            <label>
                                Nota fiscal
                            </label>

                            <input
                                type="text"
                                name="invoice_number"
                                value="{{ old('invoice_number') }}"
                                placeholder="Ex: NF 12345"
                            >
                        </div>

                    </div>


                    <div class="form-group">
                        <label>
                            Observações
                        </label>

                        <textarea
                            name="notes"
                            rows="3"
                            placeholder="Informações adicionais sobre a entrada..."
                        >{{ old('notes') }}</textarea>
                    </div>

                </div>


                <div class="tire-entry-individual-actions">
                    <button
                        type="submit"
                        class="workshop-submit-btn"
                    >
                        <i class="bi bi-floppy"></i>

                        Registrar pneu
                    </button>
                </div>

            </form>

        </div>

    </section>

</div>

@endsection
