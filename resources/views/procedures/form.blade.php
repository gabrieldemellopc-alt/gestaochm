@php

    $isEdit =
        isset($procedure)
        && $procedure;

    $fieldsData =

        $isEdit

            ? $procedure
                ->fields
                ->map(function($field){

                    return [

                        'label' =>
                            $field->label,

                        'slug' =>
                            $field->slug,

                        'field_type' =>
                            $field->field_type,

                        'stock_category_id' =>
                            $field->stock_category_id,

                        'has_quantity' =>
                            (bool) $field->has_quantity,

                        'required' =>
                            (bool) $field->required,
                    ];

                })
                ->values()
                ->toArray()

            : [];

@endphp

<div

    x-data="{

        validityKm:
            {{ $isEdit && $procedure->validity_km ? 'true' : 'false' }},

        validityHours:
            {{ $isEdit && $procedure->validity_hours ? 'true' : 'false' }},

        validityPeriod:
            {{ $isEdit && $procedure->validity_period ? 'true' : 'false' }},

        fields:
            {{ Js::from($fieldsData) }}

    }"

>

<form
    method="POST"

    action="
        {{

            $isEdit

                ? route(
                    'procedures.update',
                    $procedure->id
                )

                : route(
                    'procedures.store'
                )

        }}
    "
>

    @csrf

    @if($isEdit)

        @method('PUT')

    @endif

    <div class="page-header">

        <div>

            <h1 class="page-title">

                {{

                    $isEdit

                        ? 'Editar procedimento'

                        : 'Novo procedimento'

                }}

            </h1>

            <p class="page-subtitle">

                Configure regras, validade e campos

            </p>

        </div>

    </div>

    <div class="form-card">

        {{-- DADOS --}}
        <div class="top-form-grid">
            {{-- NOME --}}
            <div class="form-group">

                <label>
                    Nome do procedimento
                </label>

                <input
                    type="text"
                    name="name"
                    class="form-input"
                    placeholder="Ex: Troca de óleo"

                    value="{{ old('name', $procedure->name ?? '') }}"
                >

            </div>
            
            {{-- OFICINA --}}
            <div class="form-group">

                <label>
                    Oficina interna?
                </label>

                <select
                    name="can_be_internal"
                    class="form-input"
                >

                    <option
                        value="1"

                        @selected(
                            old(
                                'can_be_internal',
                                $procedure->can_be_internal ?? 1
                            ) == 1
                        )
                    >

                        Sim

                    </option>

                    <option
                        value="0"

                        @selected(
                            old(
                                'can_be_internal',
                                $procedure->can_be_internal ?? 1
                            ) == 0
                        )
                    >

                        Não

                    </option>

                </select>

            
            </div>
            {{-- VALIDADE --}}
            <div class="form-group validity-section">
                    
                    <label>
                        Regras de validade
                    </label>
                
                        <div class="validity-cards">
                        
                            {{-- KM --}}
                            <div class="validity-card">
                        
                                <label class="nf-check">
                        
                                    <input
                                        type="checkbox"
                                        name="validity_km"
                                        value="1"
                        
                                        x-model="validityKm"
                        
                                        @change="
                                            if(!validityKm)
                                                $refs.intervalKm.value = ''
                                        "
                                    >
                        
                                    <span>
                                        Controle por KM
                                    </span>
                        
                                </label>
                        
                                <input
                                    type="number"
                                    name="interval_km"
                                    class="form-input"
                                    placeholder="Ex: 10000"
                        
                                    x-ref="intervalKm"
                        
                                    x-show="validityKm"
                                    x-transition
                                    x-cloak
                        
                                    :value="
                                        {{
                                            old(
                                                'interval_km',
                                                $procedure->interval_km ?? 0
                                            )
                                        }}
                                    "
                                >
                        
                            </div>
                        
                            {{-- HORAS --}}
                            <div class="validity-card">
                        
                                <label class="nf-check">
                        
                                    <input
                                        type="checkbox"
                                        name="validity_hours"
                                        value="1"
                        
                                        x-model="validityHours"
                        
                                        @change="
                                            if(!validityHours)
                                                $refs.intervalHours.value = ''
                                        "
                                    >
                        
                                    <span>
                                        Controle por Horas
                                    </span>
                        
                                </label>
                        
                                <input
                                    type="number"
                                    name="interval_hours"
                                    class="form-input"
                                    placeholder="Ex: 500"
                        
                                    x-ref="intervalHours"
                        
                                    x-show="validityHours"
                                    x-cloak
                                    x-transition
                                    :value="
                                        {{
                                            old(
                                                'interval_hours',
                                                $procedure->interval_hours ?? 0
                                            )
                                        }}
                                    "
                                >
                        
                            </div>
                        
                            {{-- PERÍODO --}}
                            <div class="validity-card">
                        
                                <label class="nf-check">
                        
                                    <input
                                        type="checkbox"
                                        name="validity_period"
                                        value="1"
                        
                                        x-model="validityPeriod"
                        
                                        @change="
                                            if(!validityPeriod)
                                                $refs.intervalDays.value = ''
                                        "
                                    >
                        
                                    <span>
                                        Controle por Período
                                    </span>
                        
                                </label>
                        
                                <input
                                    type="number"
                                    name="interval_days"
                                    class="form-input"
                                    placeholder="Ex: 180 dias"
                        
                                    x-ref="intervalDays"
                        
                                    x-show="validityPeriod"
                                    x-cloak
                                    x-transition
                                    :value="
                                        {{
                                            old(
                                                'interval_days',
                                                $procedure->interval_days ?? 0
                                            )
                                        }}
                                    "
                                >
                        
                            </div>
                        
                        </div>            
                </div>
            
        </div>

        {{-- BUILDER --}}
        <div class="fields-builder">

            <div class="builder-header">

                <h3>
                    Campos do procedimento
                </h3>

                <button
                    type="button"
                    class="btn-secondary"

                    @click="fields.push({

                        label: '',

                        slug: '',

                        field_type: 'text',

                        stock_category_id: '',

                        has_quantity: false,

                        required: true

                    })"
                >

                    + Adicionar campo

                </button>

            </div>

            <template
                x-for="(field, index) in fields"
                :key="index"
            >

                <div class="field-card">

                    <div class="field-grid">

                        {{-- LABEL --}}
                        <input
                            type="text"

                            :name="'fields[' + index + '][label]'"

                            x-model="field.label"

                            class="form-input"

                            placeholder="Nome do campo"
                        >

                        {{-- TIPO --}}
                        <select
                            :name="'fields[' + index + '][field_type]'"

                            x-model="field.field_type"

                            class="form-input"
                        >

                            <option value="text">
                                Texto
                            </option>

                            <option value="number">
                                Número
                            </option>

                            <option value="stock_item">
                                Item estoque
                            </option>

                        </select>

                        {{-- CATEGORIA --}}
                        <select

                            x-show="
                                field.field_type === 'stock_item'
                            "

                            :name="'fields[' + index + '][stock_category_id]'"

                            x-model="field.stock_category_id"

                            class="form-input"
                        >

                            <option value="">
                                Categoria estoque
                            </option>

                            @foreach($categories as $category)

                                <option
                                    value="{{ $category->id }}"
                                >

                                    {{ $category->name }}

                                </option>

                            @endforeach

                        </select>

                        {{-- QUANTIDADE --}}
                        <label
                            class="nf-check"

                            x-show="
                                field.field_type === 'stock_item'
                            "
                        >

                            <input
                                type="checkbox"

                                :name="'fields[' + index + '][has_quantity]'"

                                value="1"

                                x-model="field.has_quantity"
                            >

                            <span>
                                Controlar quantidade
                            </span>

                        </label>

                        {{-- REQUIRED --}}
                        <label class="nf-check">

                            <input
                                type="checkbox"

                                :name="'fields[' + index + '][required]'"

                                value="1"

                                x-model="field.required"
                            >

                            <span>
                                Obrigatório
                            </span>

                        </label>

                        {{-- REMOVER --}}
                        <button
                            type="button"
                            class="btn-danger"

                            @click="
                                fields.splice(index, 1)
                            "
                        >

                            Remover

                        </button>

                    </div>

                </div>

            </template>

        </div>

        {{-- ACTIONS --}}
        <div class="form-actions">

            <button
                type="submit"
                class="btn-primary"
            >

                {{

                    $isEdit

                        ? 'Salvar alterações'

                        : 'Salvar procedimento'

                }}

            </button>

        </div>

    </div>

</form>

</div>