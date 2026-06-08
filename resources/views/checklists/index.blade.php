@extends('layouts.app')

@push('styles')

<link
    rel="stylesheet"
    href="{{ asset('css/custom.css') }}"
>

<link
    rel="stylesheet"
    href="{{ asset('css/pages/checklists.css') }}"
>

@endpush

@section('content')
<div class="checklists-page">

    <div class="page-hero">

        <div>

            <small>
                Gestão operacional
            </small>

            <h1>
                Checklists
            </h1>

            <p>
                Templates operacionais da frota
            </p>

        </div>

    </div>

    <div class="checklists-grid">

        @foreach($templates as $template)
        
            @php
        
                $activeItems =
                    $template->items
                        ->where('is_active', true);
        
            @endphp
        
            @if($activeItems->count())
            <div class="checklist-card">

                <span class="checklist-status active">
                    Ativo
                </span>

                <div class="checklist-card-top">

                    <div>

                        <h3>
                            {{ $template->name }}
                        </h3>

                        <small>

                            @switch($template->type)

                                @case('driver_pre')
                                    Motorista • Pré-operação
                                @break

                                @case('driver_post')
                                    Motorista • Pós-operação
                                @break

                                @case('manager_daily')
                                    Gestor • Diário
                                @break

                                @default
                                    {{ $template->type }}

                            @endswitch

                        </small>

                    </div>

                    <span class="checklist-badge">

                        {{ $template->items->count() }} itens

                    </span>

                </div>

                <div class="checklist-items-preview">

                        @foreach(
                            $template->items
                                ->where('is_active', true)
                            as $item
                        )
                        <div class="checklist-item-row">

                            <div class="checklist-item-left">

                                <i data-lucide="check"></i>

                                <span>
                                    {{ $item->label }}
                                </span>

                            </div>

                            <span class="checklist-item-type">

                                @switch($item->field_type)

                                    @case('boolean')
                                        Sim/Não
                                    @break

                                    @case('text')
                                        Texto
                                    @break

                                    @case('number')
                                        Numérico
                                    @break

                                    @case('photo')
                                        Foto
                                    @break

                                    @default
                                        {{ $item->field_type }}

                                @endswitch

                            </span>

                        </div>

                    @endforeach
                    <button
                        type="button"
                        class="checklist-config-button"
                        onclick="
                            openChecklistConfig(
                                {{ $template->id }}
                            )
                        "
                    >
                    
                        <i data-lucide="settings-2"></i>
                    
                        Configurar
                    
                    </button>
                </div>

            </div>

            @endif
        
        @endforeach
    </div>

</div>

@foreach($templates as $template)

<div
    class="nf-modal"
    id="configModal{{ $template->id }}"
>

    <div
        class="nf-modal-backdrop"
        onclick="
            closeChecklistConfig(
                {{ $template->id }}
            )
        "
    ></div>

    <div class="nf-modal-content medium">

        <div class="nf-modal-header">

            <div>

                <h3>
                    {{ $template->name }}
                </h3>

                <p>
                    Configuração operacional
                </p>

            </div>

            <button
                class="nf-modal-close"
                type="button"
                onclick="
                    closeChecklistConfig(
                        {{ $template->id }}
                    )
                "
            >

                <i data-lucide="x"></i>

            </button>

        </div>

        <div class="checklist-config-list">

            @foreach($template->items as $item)

                <div class="config-item-row">

                    <div>

                        <strong>
                            {{ $item->label }}
                        </strong>

                        <small>

                            @switch($item->field_type)
                            
                                @case('boolean')
                                    Sim ou Não
                                @break
                            
                                @case('number')
                                    Campo numérico
                                @break
                            
                                @case('text')
                                    Campo de texto
                                @break
                            
                                @case('photo')
                                    Envio de foto
                                @break
                            
                                @default
                                    {{ $item->field_type }}
                            
                            @endswitch

                        </small>

                    </div>

                    <button
                        type="button"
                        class="
                            config-toggle
                            {{ $item->is_active ? 'active' : '' }}
                        "
                        data-id="{{ $item->id }}"
                        data-active="{{ $item->is_active ? 1 : 0 }}"
                    >

                        <span></span>

                    </button>

                </div>

            @endforeach
            <div class="checklist-config-actions">
            
                <button
                    type="button"
                    class="checklist-save-button"
                    onclick="
                        saveChecklistConfig(
                            {{ $template->id }}
                        )
                    "
                >
            
                    <i data-lucide="save"></i>
            
                    Salvar alterações
            
                </button>
            
            </div>
        </div>

    </div>

</div>

@endforeach

@endsection

@push('scripts')

<script>

function openChecklistConfig(id)
{
    document
        .getElementById(
            'configModal'+id
        )
        .classList
        .add('active');
}

function closeChecklistConfig(id)
{
    document
        .getElementById(
            'configModal'+id
        )
        .classList
        .remove('active');
}

</script>
<script>

const checklistChanges = {};

document.addEventListener(
    'click',
    function(e)
    {
        const toggle =
            e.target.closest('.config-toggle');

        if(!toggle)
        {
            return;
        }

        toggle.classList.toggle('active');

        const isActive =
            toggle.classList.contains(
                'active'
            );

        toggle.dataset.active =
            isActive ? 1 : 0;

        checklistChanges[
            toggle.dataset.id
        ] = isActive ? 1 : 0;
    }
);

async function saveChecklistConfig()
{
    const csrf =
        document
            .querySelector(
                'meta[name="csrf-token"]'
            )
            .getAttribute('content');

    const entries =
        Object.entries(checklistChanges);

    for(const [id, active] of entries)
    {
        await fetch(
            '/checklists/items/toggle',
            {
                method: 'POST',

                headers: {

                    'Content-Type':
                        'application/json',

                    'X-CSRF-TOKEN':
                        csrf,

                    'Accept':
                        'application/json'

                },

                body: JSON.stringify({

                    item_id: id,

                    active: active

                })
            }
        );
    }

    window.location.reload();
}

</script>
@endpush


