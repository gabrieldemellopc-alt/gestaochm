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

<div class="checklist-execution-page">

    <div class="execution-header">

        <div>

            <small>
                Execução operacional
            </small>

            <h1>
                {{ $template->name }}
            </h1>

            <p>

                {{ $vehicle->name }}
                •
                {{ $vehicle->plate }}

            </p>

        </div>

        <div class="execution-header-badge">

            {{ now()->format('d/m/Y H:i') }}

        </div>

    </div>

    <form
        method="POST"
        action="{{ route(
            'checklist-executions.store',
            [
                $vehicle,
                $template
            ]
        ) }}"
        enctype="multipart/form-data"
        class="execution-form"
    >

        @csrf

        <div class="execution-items-list">

            @foreach($template->items as $item)

                <div class="execution-item-card">

                    <div class="execution-item-top">

                        <div>

                            <h3>
                                {{ $item->label }}
                            </h3>

                            <small>

                                @switch($item->field_type)

                                    @case('boolean')
                                        Resposta de confirmação
                                    @break

                                    @case('number')
                                        Campo numérico
                                    @break

                                    @case('text')
                                        Texto livre
                                    @break

                                    @case('photo')
                                        Registro fotográfico
                                    @break

                                @endswitch

                            </small>

                        </div>

                    </div>

                    {{-- BOOLEAN --}}
                    @if($item->field_type === 'boolean')

                        <div class="boolean-options">

                            <label class="boolean-option">

                                <input
                                    type="radio"
                                    name="item_{{ $item->id }}"
                                    value="Sim"
                                    required
                                >

                                <span>
                                    Sim
                                </span>

                            </label>

                            <label class="boolean-option">

                                <input
                                    type="radio"
                                    name="item_{{ $item->id }}"
                                    value="Não"
                                >

                                <span>
                                    Não
                                </span>

                            </label>

                        </div>

                    @endif

                    {{-- NUMBER --}}
                    @if($item->field_type === 'number')

                        <input
                            type="number"
                            name="item_{{ $item->id }}"
                            class="nf-input"
                            required
                        >

                    @endif

                    {{-- TEXT --}}
                    @if($item->field_type === 'text')

                        <textarea
                            name="item_{{ $item->id }}"
                            class="nf-textarea"
                            rows="4"
                        ></textarea>

                    @endif

                    {{-- PHOTO --}}
                    @if($item->field_type === 'photo')

                        <input
                            type="file"
                            accept="image/*"
                            capture="environment"
                            name="item_{{ $item->id }}"
                            class="nf-file-input"
                        >

                    @endif

                </div>

            @endforeach

        </div>

        <div class="execution-submit">

            <button
                type="submit"
                class="execution-submit-button"
            >

                <i data-lucide="clipboard-check"></i>

                Finalizar checklist

            </button>

        </div>

    </form>

</div>

@endsection