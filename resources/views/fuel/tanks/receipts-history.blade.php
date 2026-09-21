@extends('layouts.app')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/pages/fuel.css') }}?v=4">
@endpush

@section('content')

<main
    class="fuel-page fuel-history-page"
    x-data="{
        cancelId: null,
        editId: @js(session('edit_receipt_id'))
    }"
>

@php
    $hasFilters = request()->filled([
        'start_date',
        'end_date',
        'fuel_product_id',
        'fuel_tank_id',
        'supplier_name',
        'status',
        'search',
    ]);
@endphp


<header class="fuel-header">

    <div>
        <span class="fuel-kicker">
            Recebimentos
        </span>

        <h1>
            Histórico completo de entradas
        </h1>

        <p>
            Consulte entradas, documentos fiscais
            e correções da unidade ativa.
        </p>
    </div>

    <div class="fuel-header-actions">

        <a
            href="{{ route('fuel.tanks.index') }}"
            class="fuel-secondary-action"
        >
            Voltar
        </a>

    </div>

</header>


<form
    class="fuel-history-filters fuel-receipts-history-filters"
    method="GET"
>

    <label class="fuel-history-filter-field fuel-history-filter-search fuel-receipts-filter-search">
        <span>Buscar</span>

        <div class="fuel-history-search-box">
            <i class="bi bi-search"></i>

            <input
                type="text"
                name="search"
                value="{{ request('search') }}"
                placeholder="Fornecedor, NF, responsável, litros..."
            >
        </div>
    </label>


    <label class="fuel-history-filter-field fuel-receipts-filter-start">
        <span>Data inicial</span>

        <input
            type="date"
            name="start_date"
            value="{{ request('start_date') }}"
        >
    </label>


    <label class="fuel-history-filter-field fuel-receipts-filter-end">
        <span>Data final</span>

        <input
            type="date"
            name="end_date"
            value="{{ request('end_date') }}"
        >
    </label>


    <label class="fuel-history-filter-field fuel-receipts-filter-product">
        <span>Produto</span>

        <select name="fuel_product_id">
            <option value="">
                Todos
            </option>

            @foreach($products as $product)
                <option
                    value="{{ $product->id }}"
                    @selected(
                        request('fuel_product_id')
                        == $product->id
                    )
                >
                    {{ $product->name }}
                </option>
            @endforeach
        </select>
    </label>


    <label class="fuel-history-filter-field fuel-receipts-filter-tank">
        <span>Tanque</span>

        <select name="fuel_tank_id">
            <option value="">
                Todos
            </option>

            @foreach($tanks as $tank)
                <option
                    value="{{ $tank->id }}"
                    @selected(
                        request('fuel_tank_id')
                        == $tank->id
                    )
                >
                    {{ $tank->name }}
                </option>
            @endforeach
        </select>
    </label>


    <label class="fuel-history-filter-field fuel-receipts-filter-status">
        <span>Status</span>

        <select name="status">
            <option value="">
                Todos
            </option>

            <option
                value="active"
                @selected(request('status') === 'active')
            >
                Realizado
            </option>

            <option
                value="cancelled"
                @selected(request('status') === 'cancelled')
            >
                Cancelado
            </option>
        </select>
    </label>


    <div class="fuel-history-filter-actions">
        <button class="fuel-primary-action">
            Filtrar
        </button>

        @if($hasFilters)
            <a
                href="{{ route('fuel.receipts.history') }}"
                class="fuel-secondary-action"
            >
                Limpar
            </a>
        @endif
    </div>

</form>


<section class="fuel-panel">

<div class="fuel-table-wrap">

<table class="fuel-table fuel-receipts-history-table">

<thead>
<tr>
    <th>Data / tanque</th>
    <th>Produto</th>
    <th>Quantidade / valor</th>
    <th>Fornecedor / NF</th>
    <th>Responsável</th>
    <th>Status</th>
    <th>Ações</th>
</tr>
</thead>

<tbody>

@forelse($receipts as $receipt)

@php
    $documentPending =
        ! $receipt->cancelled_at
        && (
            $receipt->invoice_pending
            || (
                ($fuelReceiptInvoiceRequired ?? false)
                && blank($receipt->invoice_number)
            )
        );
@endphp

<tr class="{{ $receipt->cancelled_at ? 'is-cancelled' : '' }}">

    <td class="fuel-history-date fuel-receipt-history-date">

        <strong>
            {{ $receipt->received_at?->format('d/m/Y') }}
        </strong>

        <small>
            {{ $receipt->received_at?->format('H:i') }}
        </small>

        <small class="fuel-receipt-history-tank">
            {{ $receipt->tank?->name }}
        </small>

    </td>


    <td>
        {{ $receipt->product?->name }}
    </td>


    <td>
        <strong>
            {{ number_format(
                (float) $receipt->quantity_liters,
                3,
                ',',
                '.'
            ) }} L
        </strong>

        @if($fuelPermissions['view_costs'])
            <small>
                R$
                {{ number_format(
                    (float) $receipt->total_cost,
                    2,
                    ',',
                    '.'
                ) }}
            </small>
        @endif
    </td>


    <td>

        <strong>
            {{ $receipt->supplier_name ?: '—' }}
        </strong>

        <br>

        @if($receipt->invoice_number)

            <span>
                NF {{ $receipt->invoice_number }}
            </span>

            @if($receipt->invoice_date)
                <small>
                    · {{ $receipt->invoice_date->format('d/m/Y') }}
                </small>
            @endif

        @else

            <span class="fuel-receipt-document-missing">
                Sem documento
            </span>

        @endif

    </td>


    <td>
        {{ $receipt->responsible?->name ?: '—' }}
    </td>


    <td>

        @if($receipt->cancelled_at)

            <span class="fuel-history-status is-cancelled">
                @if($receipt->replaced_by_receipt_id)
                    Substituído
                @else
                    Cancelado
                @endif
            </span>

            @if($receipt->replaced_by_receipt_id)
                <small>
                    pelo #{{ $receipt->replaced_by_receipt_id }}
                </small>
            @endif

            @if($receipt->cancel_reason)
                <small>
                    {{ $receipt->cancel_reason }}
                </small>
            @endif

        @else

            <span class="fuel-history-status is-complete">
                Realizado
            </span>

            @if($documentPending)
                <span class="fuel-history-status is-pending">
                    NF pendente
                </span>
            @endif

            @if($receipt->replaces_receipt_id)
                <small>
                    Corrige o #{{ $receipt->replaces_receipt_id }}
                </small>
            @endif

        @endif

    </td>


    <td>

        @if(! $receipt->cancelled_at)

            @if($fuelPermissions['receive'])

                <button
                    type="button"
                    class="fuel-secondary-action"
                    x-on:click="editId = {{ $receipt->id }}"
                >
                    Editar
                </button>

            @endif


            @if($fuelPermissions['cancel'])

                <button
                    type="button"
                    class="fuel-secondary-action fuel-cancel-action"
                    x-on:click="cancelId = {{ $receipt->id }}"
                >
                    Cancelar
                </button>

            @endif

        @endif

    </td>

</tr>

@empty

<tr>
    <td colspan="7">
        Nenhum recebimento encontrado.
    </td>
</tr>

@endforelse

</tbody>

</table>

</div>

{{ $receipts->links() }}

</section>


{{-- MODAIS DE EDIÇÃO --}}

@foreach($receipts as $receipt)

@if(! $receipt->cancelled_at && $fuelPermissions['receive'])

<div
    class="fuel-modal-overlay"
    :class="{ 'is-open': editId === {{ $receipt->id }} }"
>

<div class="fuel-modal-card fuel-receipt-edit-modal">

    <div class="fuel-modal-header">

        <div>
            <span class="fuel-kicker">
                Correção auditável
            </span>

            <h2>
                Editar recebimento #{{ $receipt->id }}
            </h2>

            <p>
                O lançamento atual será mantido no histórico
                como substituído e um novo será criado.
            </p>
        </div>

        <button
            type="button"
            class="fuel-modal-close"
            x-on:click="editId = null"
        >
            <i class="bi bi-x-lg"></i>
        </button>

    </div>


    <form
        method="POST"
        action="{{ route(
            'fuel.receipts.replace',
            $receipt
        ) }}"
        class="fuel-form"
    >

        @csrf

        <div class="fuel-form-grid">

            <div class="form-group">
                <label>
                    Data/hora do recebimento
                </label>

                <input
                    type="datetime-local"
                    name="received_at"
                    class="form-input"
                    value="{{ old(
                        'received_at',
                        $receipt->received_at?->format('Y-m-d\TH:i')
                    ) }}"
                    required
                >
            </div>


            <div class="form-group">
                <label>
                    Quantidade (L)
                </label>

                <input
                    type="number"
                    step="0.001"
                    min="0.001"
                    name="quantity_liters"
                    class="form-input"
                    value="{{ old(
                        'quantity_liters',
                        $receipt->quantity_liters
                    ) }}"
                    required
                >
            </div>


            @if($fuelPermissions['view_costs'])

            <div class="form-group">
                <label>
                    Valor total
                </label>

                <input
                    type="number"
                    step="0.01"
                    min="0"
                    name="total_cost"
                    class="form-input"
                    value="{{ old(
                        'total_cost',
                        $receipt->total_cost
                    ) }}"
                >
            </div>

            @endif


            <div class="form-group">
                <label>
                    Fornecedor
                </label>

                <input
                    type="text"
                    name="supplier_name"
                    class="form-input"
                    value="{{ old(
                        'supplier_name',
                        $receipt->supplier_name
                    ) }}"
                >

                <input
                    type="hidden"
                    name="supplier_id"
                    value="{{ $receipt->supplier_id }}"
                >

                <input
                    type="hidden"
                    name="supplier_document"
                    value="{{ $receipt->supplier_document }}"
                >
            </div>


            <div class="form-group">
                <label>
                    Número da NF
                </label>

                <input
                    type="text"
                    name="invoice_number"
                    class="form-input"
                    value="{{ old(
                        'invoice_number',
                        $receipt->invoice_number
                    ) }}"
                >
            </div>


            <div class="form-group">
                <label>
                    Data da NF
                </label>

                <input
                    type="date"
                    name="invoice_date"
                    class="form-input"
                    value="{{ old(
                        'invoice_date',
                        $receipt->invoice_date?->format('Y-m-d')
                    ) }}"
                >
            </div>

        </div>


        <div class="form-group">
            <label>
                Observações
            </label>

            <textarea
                name="notes"
                class="form-input"
            >{{ old('notes', $receipt->notes) }}</textarea>
        </div>


        <div class="form-group">
            <label>
                Motivo da correção
            </label>

            <textarea
                name="reason"
                class="form-input"
                required
                minlength="5"
                placeholder="Ex.: valor estimado substituído pelo valor correto da nota fiscal."
            >{{ old('reason') }}</textarea>
        </div>


        <div class="fuel-form-actions">

            <button
                type="button"
                class="fuel-secondary-action"
                x-on:click="editId = null"
            >
                Voltar
            </button>

            <button class="fuel-primary-action">
                Salvar correção
            </button>

        </div>

    </form>

</div>

</div>

@endif


{{-- CANCELAMENTO --}}

@if(! $receipt->cancelled_at && $fuelPermissions['cancel'])

<div
    class="fuel-modal-overlay"
    :class="{ 'is-open': cancelId === {{ $receipt->id }} }"
>

<div class="fuel-modal-card">

    <h2>
        Cancelar recebimento #{{ $receipt->id }}
    </h2>

    <p>
        Informe o motivo.
        O lançamento será mantido no histórico para auditoria.
    </p>

    <form
        method="POST"
        action="{{ route(
            'fuel.receipts.cancel',
            $receipt
        ) }}"
        class="fuel-form"
    >

        @csrf

        <textarea
            name="reason"
            required
            minlength="5"
            placeholder="Motivo do cancelamento"
        >{{ old('reason') }}</textarea>

        <div class="fuel-form-actions">

            <button
                type="button"
                class="fuel-secondary-action"
                x-on:click="cancelId = null"
            >
                Voltar
            </button>

            <button class="fuel-primary-action">
                Cancelar lançamento
            </button>

        </div>

    </form>

</div>

</div>

@endif

@endforeach

</main>

@endsection
