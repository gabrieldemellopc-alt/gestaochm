@extends('layouts.app')



@push('styles')

<link rel="stylesheet" href="{{ asset('css/pages/stock.css') }}?v=3">
<link rel="stylesheet" href="{{ asset('css/pages/fiscal-document-import-stock.css') }}?v=2">

@endpush



@section('content')
@php
    $stockPermissions = array_merge([
        'view' => false,
        'view_item_details' => false,
        'manage_categories' => false,
        'manage_items' => false,
        'create_entry' => false,
        'create_manual_output' => false,
        'cancel_movement' => false,
        'view_costs' => false,
        'import_invoice' => false,
    ], $stockPermissions ?? []);

    $canManageStockCategories = (bool) $stockPermissions['manage_categories'];
    $canViewStockItemDetails = (bool) $stockPermissions['view_item_details'];
    $canManageStockItems = (bool) $stockPermissions['manage_items'];
    $canCreateStockEntry = (bool) $stockPermissions['create_entry'];
    $canCreateStockOutput = (bool) $stockPermissions['create_manual_output'];
    $canCancelStockMovement = (bool) $stockPermissions['cancel_movement'];
    $canViewStockCosts = (bool) $stockPermissions['view_costs'];
    $canImportFiscalDocument = (bool) $stockPermissions['import_invoice'];
@endphp

<div class="stock-page">



    {{-- HEADER --}}

    {{-- HEADER --}}

    <div class="stock-header stock-header-modern">



        <div>



            <span class="stock-kicker">

                Oficina / Estoque

            </span>



            <h1>

                Estoque

            </h1>



            <p>

                Controle de categorias, itens e movimentações operacionais da oficina.

            </p>



        </div>



        <div class="stock-header-actions">



            <a

                href="{{ route('workshop.index') }}"

                class="chm-page-button secondary"

            >

                <i data-lucide="arrow-left"></i>

                Voltar para oficina

            </a>

            <button type="button" class="chm-page-button secondary" onclick="window.openStockDashboard()">
                <i data-lucide="bar-chart-3"></i>
                Painel de estoque
            </button>

            @if($canImportFiscalDocument)
                <button
                    type="button"
                    class="chm-page-button secondary stock-import-invoice-trigger"
                    onclick="window.dispatchEvent(new CustomEvent('open-fiscal-import'))"
                >
                    <i data-lucide="file-up"></i>
                    Importar NF
                </button>
            @endif


            @if($canManageStockCategories)
<button

                type="button"

                class="chm-page-button primary"

                onclick="openCategoryModal()"

            >

                <i data-lucide="plus"></i>

                Nova categoria

            </button>
@endif



        </div>



    </div>

    {{-- RESUMO --}}

    <div class="stock-summary-grid">



        <div class="stock-summary-card">



            <div class="stock-summary-icon">

                <i data-lucide="boxes"></i>

            </div>



            <div>

                <span>

                    Categorias

                </span>



                <strong>

                    {{ $categories->count() }}

                </strong>

            </div>



        </div>



        <div class="stock-summary-card">



            <div class="stock-summary-icon">

                <i data-lucide="package"></i>

            </div>



            <div>

                <span>

                    Itens cadastrados

                </span>



                <strong>

                    {{ $categories->sum(fn($category) => $category->items->count()) }}

                </strong>

            </div>



        </div>



        <div class="stock-summary-card warning">



            <div class="stock-summary-icon">

                <i data-lucide="triangle-alert"></i>

            </div>



            <div>

                <span>

                    Atenção no estoque

                </span>



                <strong>

                    {{

                        $categories->sum(function ($category) {

                            return $category->items->whereIn('stock_status', ['warning', 'danger'])->count();

                        })

                    }}

                </strong>

            </div>



        </div>



    </div>



    {{-- CATEGORIAS --}}

    <div class="stock-wrapper">



        @forelse($categories as $category)



            <div class="stock-category-card">



                <div class="stock-category-header">



                    <div class="stock-category-title">



                        <div class="stock-category-icon">

                            <i data-lucide="folder-kanban"></i>

                        </div>



                        <div>



                            <h2>

                                {{ $category->name }}

                            </h2>



                            <span>

                                {{ $category->items_count }}

                                item(ns) cadastrado(s)

                            </span>



                        </div>



                    </div>



                    @if($canManageStockItems)
<button

                        type="button"

                        class="stock-add-item-btn"

                        onclick="

                            openItemModal(

                                {{ $category->id }},

                                '{{ $category->name }}'

                            )

                        "

                    >

                        <i data-lucide="plus"></i>



                        Novo item

</button>
@endif

                    @if($canManageStockCategories)
                        <div class="stock-category-actions">
                            <button type="button" class="stock-category-action" onclick='openCategoryEditModal({{ $category->id }}, @json($category->name))'>
                                <i data-lucide="pencil"></i>
                                Editar
                            </button>
                            <button
                                type="button"
                                class="stock-category-action danger"
                                @disabled($category->items_count > 0 || $category->other_location_items_count > 0)
                                title="{{ $category->items_count > 0 ? 'Categoria em uso por '.$category->items_count.' itens nesta unidade' : ($category->other_location_items_count > 0 ? 'Categoria compartilhada e em uso por '.$category->other_location_items_count.' itens em outra unidade' : 'Excluir categoria') }}"
                                onclick='openCategoryDeleteModal({{ $category->id }}, @json($category->name))'
                            >
                                <i data-lucide="trash-2"></i>
                                Excluir
                            </button>
                        </div>
                    @endif



                </div>



                <div class="stock-items-grid">



                    @forelse($category->items as $item)



                        <div

                            class="

                                stock-item-card

                                {{ $item->stock_status }}

                            "

                            onclick="openEditItemModal({{ $item->id }})"

                        >



                            <div class="stock-item-top">



                                <div class="stock-item-icon">

                                    <i data-lucide="package"></i>

                                </div>



                                <div>



                                    <h3>

                                        {{ $item->name }}

                                    </h3>



                                    <span>

                                        {{ $item->brand ?: 'Sem marca' }}

                                    </span>



                                </div>

                                @if($item->stock_status === 'danger')



                                    <span class="stock-status-badge stock-item-status danger">

                                        <i data-lucide="circle-alert"></i>

                                        Crítico

                                    </span>



                                @elseif($item->stock_status === 'warning')



                                    <span class="stock-status-badge stock-item-status warning">

                                        <i data-lucide="triangle-alert"></i>

                                        Atenção

                                    </span>



                                @else



                                    <span class="stock-status-badge stock-item-status ok">

                                        <i data-lucide="check-circle"></i>

                                        Adequado

                                    </span>



                                @endif



                            </div>



                            <div class="stock-item-values stock-item-values-clean">

                                <div>
                                    <span>Estoque atual</span>
                                    <strong class="stock-current-value">
                                        <span class="stock-current-number">
                                            {{ number_format($item->quantity, 2, ',', '.') }}
                                        </span>

                                        <span class="stock-current-unit">
                                            {{ $item->unit }}
                                        </span>
                                    </strong>
                                    <small>
                                        Mínimo {{ number_format($item->minimum_quantity, 2, ',', '.') }}
                                    </small>
                                </div>

                                @if($canViewStockCosts)
<div>
                                    <span>Custo médio</span>
                                    <strong>
                                        R$ {{ number_format($item->unit_cost, 2, ',', '.') }}
                                    </strong>
                                    <small>
                                    Por {{ $item->unit }}
                                    </small>
                                </div>
@endif

                            </div>


                            <div class="stock-item-footer">

                                <div class="stock-card-actions">
                                    @if($canCreateStockEntry)
                                        <button type="button" class="stock-card-entry" onclick="event.stopPropagation(); openDirectEntry({{ $item->id }})">
                                            <i data-lucide="plus"></i>
                                            Nova entrada
                                        </button>
                                    @endif
                                </div>



                            </div>



                        </div>



                    @empty



                        <div class="empty-stock">



                            <i data-lucide="package-open"></i>



                            <strong>

                                Nenhum item nesta categoria

                            </strong>



                            <p>

                                Cadastre itens para controlar entrada, saída e saldo em estoque.

                            </p>



                            @if($canManageStockItems)
<button

                                type="button"

                                class="stock-empty-btn"

                                onclick="

                                    openItemModal(

                                        {{ $category->id }},

                                        '{{ $category->name }}'

                                    )

                                "

                            >

                                <i data-lucide="plus"></i>



                                Adicionar item

                            </button>
@endif



                        </div>



                    @endforelse



                </div>



            </div>



        @empty



            <div class="stock-empty-state">



                <i data-lucide="boxes"></i>



                <strong>

                    Nenhuma categoria cadastrada

                </strong>



                <p>

                    Comece criando uma categoria para organizar seus itens de estoque.

                </p>



                @if($canManageStockCategories)
<button

                    type="button"

                    class="chm-page-button primary"

                    onclick="openCategoryModal()"

                >

                    <i data-lucide="plus"></i>



                    Criar categoria

                </button>
@endif



            </div>



        @endforelse



    </div>



</div>



<div

    class="stock-modal-overlay"

    id="categoryModal"

    style="display:none;"

>



    <div class="stock-category-modal-card">



        <button

            type="button"

            onclick="closeCategoryModal()"

            class="stock-modal-close"

        >

            <i data-lucide="x"></i>

        </button>



        <div class="stock-modal-header">



            <div class="stock-modal-icon">

                <i data-lucide="folder-plus"></i>

            </div>



            <div>



                <span>

                    Estoque

                </span>



                <h2 id="categoryModalTitle">Nova categoria</h2>



                <p>

                    Organize os itens do estoque por tipo, finalidade ou setor.

                </p>



            </div>



        </div>



        <form

            method="POST"

            action="{{ route('stock.categories.store') }}"

            class="stock-modal-form"

            id="categoryForm"

        >



            @csrf

            <input type="hidden" name="_method" id="categoryFormMethod">



            <div class="form-group">



                <label>

                    Nome da categoria

                </label>



                <input

                    type="text"

                    name="name"

                    id="categoryName"

                    class="form-input"

                    placeholder="Ex: Óleos, Filtros, Pneus..."

                    required

                >



            </div>



            <div class="stock-modal-actions">



                <button

                    type="button"

                    class="stock-modal-cancel"

                    onclick="closeCategoryModal()"

                >

                    Cancelar

                </button>



                <button

                    class="chm-page-button primary"

                    type="submit"

                >

                    <i data-lucide="save"></i>



                    <span id="categorySubmitLabel">Salvar categoria</span>

                </button>



            </div>



        </form>



    </div>



</div>

<div class="stock-modal-overlay" id="categoryDeleteModal" style="display:none;">
    <div class="stock-category-modal-card stock-confirm-modal-card">
        <button type="button" onclick="closeCategoryDeleteModal()" class="stock-modal-close"><i data-lucide="x"></i></button>
        <div class="stock-modal-header">
            <div class="stock-modal-icon"><i data-lucide="triangle-alert"></i></div>
            <div>
                <span>Estoque</span>
                <h2>Excluir categoria</h2>
                <p id="categoryDeleteMessage">Esta ação não poderá ser desfeita.</p>
            </div>
        </div>
        <form method="POST" action="" class="stock-modal-form" id="categoryDeleteForm">
            @csrf
            @method('DELETE')
            <div class="stock-modal-actions">
                <button type="button" class="stock-modal-cancel" onclick="closeCategoryDeleteModal()">Cancelar</button>
                <button class="chm-page-button danger" type="submit"><i data-lucide="trash-2"></i> Excluir categoria</button>
            </div>
        </form>
    </div>
</div>



<div

    class="stock-modal-overlay"

    id="itemModal"

    style="display:none;"

>



    <div class="stock-item-modal-card">



        <button

            type="button"

            onclick="closeItemModal()"

            class="stock-modal-close"

        >

            <i data-lucide="x"></i>

        </button>



        <div class="stock-modal-header">



            <div class="stock-modal-icon">

                <i data-lucide="package-plus"></i>

            </div>



            <div>



                <span>

                    Estoque

                </span>



                <h2>

                    Novo item

                </h2>



                <p id="itemCategoryName">

                    Categoria selecionada

                </p>



            </div>



        </div>



        <form

            method="POST"

            action="{{ route('stock.items.store') }}"

            class="stock-modal-form"

        >



            @csrf



            <input

                type="hidden"

                name="stock_category_id"

                id="stock_category_id"

            >



            <div class="stock-item-modal-grid stock-item-create-grid">

                <div class="form-group">
                    <label>Nome do item</label>
                    <input
                        type="text"
                        name="name"
                        class="form-input"
                        placeholder="Ex: Filtro de combustível"
                        required
                    >
                </div>

                <div class="form-group">
                    <label>Marca</label>
                    <input
                        type="text"
                        name="brand"
                        class="form-input"
                        placeholder="Ex: Shell"
                    >
                </div>

                <div class="form-group">
                    <label>Unidade</label>
                    <select name="unit" class="form-input">
                        <option value="UNID">Unidade</option>
                        <option value="L">Litro</option>
                        <option value="KG">KG</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Estoque mínimo</label>
                    <input
                        type="number"
                        step="0.01"
                        min="0"
                        name="minimum_quantity"
                        class="form-input"
                        value="0"
                    >
                    <small class="stock-field-hint">
                        Quando o saldo chegar nesse limite, o sistema exibirá alerta de estoque.
                    </small>
                </div>

                <div class="form-group full-width">
                    <label class="stock-workshop-consumable-toggle">
                        <input type="checkbox" name="is_workshop_consumable" value="1">
                        <span>Consumível da oficina</span>
                    </label>
                    <small class="stock-field-hint">Permite utilizar este item em lançamentos de consumo interno da oficina, sem vínculo com veículo.</small>
                </div>

                <div class="form-group full-width">
                    <label>Observação</label>
                    <textarea
                        name="observation"
                        class="form-input"
                        rows="4"
                        placeholder="Informações adicionais sobre o item..."
                    ></textarea>
                </div>

            </div>


            <div class="stock-modal-actions">



                <button

                    type="button"

                    class="stock-modal-cancel"

                    onclick="closeItemModal()"

                >

                    Cancelar

                </button>



                <button

                    class="chm-page-button primary"

                    type="submit"

                >

                    <i data-lucide="save"></i>



                    Salvar item

                </button>



            </div>



        </form>



    </div>



</div>

<div

    class="stock-modal-overlay"

    id="editItemModal"

    style="display:none;"

>



    <div class="stock-edit-item-modal-card">

        <div class="stock-edit-modal-layout stock-item-modal-layout">

            <button type="button" onclick="closeEditItemModal()" class="stock-modal-close stock-item-modal-close" aria-label="Fechar modal">
                <i data-lucide="x"></i>
            </button>

            <aside class="stock-edit-sidebar stock-item-modal-sidebar">
                <div class="stock-edit-header-new stock-item-modal-identity">
                    <div class="stock-modal-icon">
                        <i data-lucide="package"></i>
                    </div>
                    <div>
                        <span id="editItemCategory"></span>
                        <h2 id="editItemName"></h2>
                    </div>
                </div>
                <div class="stock-balance-card-new stock-item-modal-summary">
                    <div class="stock-balance-icon"><i data-lucide="package-check"></i></div>
                    <span>Estoque atual</span>
                    <h2 id="editStockQuantity">0</h2>
                    <small id="editItemUnitBadge">Unidade</small>
                </div>

                @if($canCreateStockEntry || $canCreateStockOutput)
                <div class="stock-movement-actions-new stock-item-modal-movement-actions">
                    @if($canCreateStockEntry)
                    <button type="button" class="stock-movement-btn in" onclick="openMovementModal('in')">
                        <i data-lucide="plus"></i> Entrada
                    </button>
                    @endif
                    @if($canCreateStockOutput)
                    <button type="button" class="stock-movement-btn out" onclick="openMovementModal('out')">
                        <i data-lucide="minus"></i> Saída
                    </button>
                    @endif
                </div>
                @endif

                <div class="stock-edit-actions-top stock-item-modal-secondary-actions">
                    @if($canManageStockItems)
                    <button type="button" class="stock-edit-trigger-btn" onclick="enableItemEdit()" id="editItemBtn">
                        <i data-lucide="pencil"></i> Editar item
                    </button>
                    @endif
                    @if($canViewStockItemDetails)
                    <a href="#" class="stock-details-link stock-modal-details-link" id="stockItemDetailsLink">
                        <i data-lucide="external-link"></i> Ver mais detalhes
                    </a>
                    @endif
                </div>
            </aside>

            <section class="stock-edit-content stock-item-modal-main">
                <div class="stock-item-modal-section-heading">
                    <span>Informações do item</span>
                    <h2>Detalhes</h2>
                </div>

                {{-- VISUALIZAÇÃO --}}

                <div class="details-view-mode">



                    <div class="stock-details-view-grid">



                        <div class="stock-detail-card">



                            <span>

                                Marca

                            </span>



                            <strong id="viewItemBrand">

                            </strong>



                        </div>



                        <div class="stock-detail-card">



                            <span>

                                Unidade

                            </span>



                            <strong id="viewItemUnit">

                            </strong>



                        </div>



                        <div class="stock-detail-card">



                            <span>

                                Estoque mínimo

                            </span>



                            <strong id="viewItemMinimum">

                            </strong>



                        </div>
                        @if($canViewStockCosts)



                        <div class="stock-detail-card">



                            <span>

                                Custo unitário

                            </span>



                            <strong id="viewItemCost">

                            </strong>



                        </div>
                        @endif



                        <div class="stock-detail-card full-width">



                            <span>

                                Observações

                            </span>



                            <p id="viewItemObservation">

                            </p>



                        </div>



                    </div>



                </div>


                <section class="stock-history-card-new stock-item-history-panel">
                    <div class="stock-history-header-new">
                        <div><span>Histórico</span><h3>Últimas movimentações</h3></div>
                        <i data-lucide="history"></i>
                    </div>
                    <div id="movementHistory" class="stock-movement-history-list stock-item-history-list"></div>
                </section>
                <div id="movementDetailPanel" class="stock-movement-detail-panel" style="display:none;">
                    <div class="stock-movement-detail-header">
                        <div class="stock-movement-detail-title-row">
                            <div id="movementDetailIcon" class="stock-modal-icon movement">
                                <i data-lucide="arrow-left-right"></i>
                            </div>

                            <div>
                                <span>Movimentação selecionada</span>
                                <h3 id="movementDetailTitle">Detalhes</h3>
                            </div>
                        </div>

                        <button type="button" onclick="closeMovementDetailPanel()">
                            <i data-lucide="x"></i>
                        </button>
                    </div>

                    <div class="stock-movement-detail-grid">
                        <div>
                            <span>Quantidade</span>
                            <strong id="movementDetailQuantity">-</strong>
                        </div>

                        @if($canViewStockCosts)
<div>
                            <span>Custo unitário</span>
                            <strong id="movementDetailUnitCost">-</strong>
                        </div>
@endif

                        @if($canViewStockCosts)
<div>
                            <span>Custo total</span>
                            <strong id="movementDetailTotalCost">-</strong>
                        </div>
@endif

                        <div>
                            <span>Data</span>
                            <strong id="movementDetailDate">-</strong>
                        </div>

                        <div>
                            <span>Fornecedor</span>
                            <strong id="movementDetailSupplier">-</strong>
                        </div>

                        <div>
                            <span>Nota fiscal</span>
                            <strong id="movementDetailInvoice">-</strong>
                        </div>

                        <div>
                            <span>Status</span>
                            <strong id="movementDetailStatus">-</strong>
                        </div>

                        <div class="span-2">
                            <span>Descrição / motivo</span>
                            <p id="movementDetailDescription">-</p>
                        </div>

                        <div class="full-width">
                            <span>Cancelamento / reversão</span>
                            <p id="movementDetailAudit">-</p>
                        </div>
                    </div>
                </div>

                {{-- EDIÇÃO --}}

                <form

                    class="details-edit-mode stock-modal-form"

                    id="editItemForm"

                    method="POST"

                    style="display:none;"

                >



                    @csrf

                    @method('PUT')



                    <div class="stock-item-modal-grid">



                        <div class="form-group">



                            <label>

                                Nome

                            </label>



                            <input

                                type="text"

                                id="inputItemName"

                                name="name"

                                class="form-input"

                            >



                        </div>

                        <div class="form-group full-width">
                            <label class="stock-workshop-consumable-toggle">
                                <input type="checkbox" id="inputItemWorkshopConsumable" name="is_workshop_consumable" value="1">
                                <span>Consumível da oficina</span>
                            </label>
                            <small class="stock-field-hint">Permite utilizar este item em lançamentos de consumo interno da oficina, sem vínculo com veículo.</small>
                        </div>



                        <div class="form-group">



                            <label>

                                Marca

                            </label>



                            <input

                                type="text"

                                id="inputItemBrand"

                                name="brand"

                                class="form-input"

                            >



                        </div>



                        <div class="form-group">



                            <label>

                                Unidade

                            </label>



                            <select

                                id="inputItemUnit"

                                name="unit"

                                class="form-input"

                            >



                                <option value="UNID">

                                    Unidade

                                </option>



                                <option value="L">

                                    Litro

                                </option>



                                <option value="KG">

                                    KG

                                </option>



                            </select>



                        </div>



                        <div class="form-group">



                            <label>

                                Estoque mínimo

                            </label>



                            <input

                                type="number"

                                step="0.01"

                                min="0"

                                id="inputItemMinimum"

                                name="minimum_quantity"

                                class="form-input"

                            >



                        </div>



                        @if($canViewStockCosts)
<div class="form-group">



                            <label>

                                Custo unitário

                            </label>



                            <input

                                type="number"

                                step="0.01"

                                min="0"

                                id="inputItemCost"

                                name="unit_cost"

                                class="form-input"

                                readonly
                            >



                        </div>
@endif



                        <div class="form-group full-width">



                            <label>

                                Observações

                            </label>



                            <textarea

                                id="inputItemObservation"

                                name="observation"

                                class="form-input"

                                rows="4"

                            ></textarea>



                        </div>



                    </div>



                    <div

                        class="stock-modal-actions"

                        id="saveItemBtn"

                        style="display:none;"

                    >



                        <button

                            type="button"

                            class="stock-modal-cancel"

                            onclick="disableItemEdit()"

                        >

                            Cancelar

                        </button>



                        <button

                            class="chm-page-button primary"

                            type="submit"

                        >

                            <i data-lucide="save"></i>



                            Salvar alterações

                        </button>



                    </div>



                </form>



            </section>



        </div>



    </div>



</div>

<div

    class="stock-modal-overlay"

    id="movementModal"

    style="display:none;"

>



    <div class="stock-movement-modal-card">



        <button

            type="button"

            onclick="closeMovementModal()"

            class="stock-modal-close"

        >

            <i data-lucide="x"></i>

        </button>



        <div class="stock-modal-header">



            <div class="stock-modal-icon movement">

                <i data-lucide="arrow-left-right"></i>

            </div>



            <div>



                <span>

                    Movimentação

                </span>



                <h2 id="movementModalTitle">

                    Nova movimentação

                </h2>



                <p id="movementModalItemName">

                    Item selecionado

                </p>



            </div>



        </div>



        <form

            id="movementForm"

            method="POST"

            action="{{ route('stock.movements.store') }}"

            class="stock-modal-form"

        >



            @csrf



            <input

                type="hidden"

                name="movement_type"

                id="movementType"

            >



            <input

                type="hidden"

                name="stock_item_id"

                id="movementItemId"

            >



        <div class="stock-movement-form-grid stock-entry-grid">

            <div class="form-group stock-span-6">
                <label>Quantidade</label>
                <input
                    type="number"
                    step="0.01"
                    min="0.01"
                    name="quantity"
                    class="form-input"
                    placeholder="Ex: 10"
                    required
                >
            </div>

            <div class="form-group stock-span-6">
                <label>Data da movimentação</label>

                <input
                    type="datetime-local"
                    name="moved_at"
                    id="movementMovedAt"
                    class="form-input"
                    value="{{ now()->format('Y-m-d\TH:i') }}"
                    required
                >
            </div>

            <div class="form-group stock-entry-only stock-span-6">
                <label>Custo total</label>
                <input
                    type="number"
                    step="0.01"
                    min="0"
                    name="total_cost"
                    id="movementTotalCost"
                    class="form-input"
                    placeholder="Ex: 3500,00"
                >
            </div>

            <input
                type="hidden"
                name="unit_cost"
                id="movementUnitCost"
            >

            <div class="form-group stock-entry-only stock-span-6">
                <label>Custo unitário calculado</label>

                <input
                    type="text"
                    id="movementUnitCostPreview"
                    class="form-input is-readonly-calculated"
                    value="R$ 0,00"
                    readonly
                >
            </div>

            <div class="form-group stock-entry-only stock-span-6">
                <label>Fornecedor</label>
                <input
                    type="text"
                    name="supplier_name"
                    class="form-input"
                    placeholder="Nome do fornecedor"
                >
            </div>

            <div class="form-group stock-entry-only stock-span-6">
                <label>Nota fiscal @if($stockEntryInvoiceRequired ?? false)<small>Obrigatório</small>@else<small>Opcional</small>@endif</label>

                <div class="input-with-badge stock-input-with-badge">
                    <span>NF</span>

                    <input
                        type="text"
                        name="invoice_number"
                        maxlength="255"
                        placeholder="12403"
                    >
                </div>
            </div>

            <div class="form-group full-width stock-span-12">
                <label id="movementDescriptionLabel">
                    Observação
                </label>

                <textarea
                    name="description"
                    id="movementDescription"
                    rows="4"
                    class="form-input"
                    placeholder="Ex: Compra de item, uso em manutenção, ajuste de estoque..."
                ></textarea>
                <small
                    id="movementDescriptionCounter"
                    class="movement-description-counter"
                    style="display:none;"
                >
                    Informe pelo menos 10 caracteres.
                </small>
            </div>

        </div>


            <div class="stock-modal-actions">



                <button

                    type="button"

                    class="stock-modal-cancel"

                    onclick="closeMovementModal()"

                >

                    Cancelar

                </button>



                <button
                    class="chm-page-button primary"
                    id="movementSubmitText"
                    type="submit"
                    onclick="return validateMovementSubmitMessage();"
                >
                    <i data-lucide="check"></i>
                    Confirmar
                </button>




            </div>



        </form>



    </div>



</div>

<div id="stockDashboardModal" class="stock-dashboard-overlay" aria-hidden="true">
    <section class="stock-dashboard-modal" role="dialog" aria-modal="true" aria-labelledby="stockDashboardTitle">
        <header class="stock-dashboard-header">
            <div><span>Estoque</span><h2 id="stockDashboardTitle">Painel de estoque</h2><p>Indicadores de estoque, entradas, saídas e consumo — período selecionado</p></div>
            <div class="stock-dashboard-controls"><select id="stockDashboardPeriod" aria-label="Período"><option value="30d">Últimos 30 dias</option><option value="current_month">Mês atual</option><option value="90d">Últimos 90 dias</option><option value="current_year">Ano atual</option></select><button type="button" onclick="window.closeStockDashboard()" aria-label="Fechar painel"><i data-lucide="x"></i></button></div>
        </header>
        <div id="stockDashboardContent" class="stock-dashboard-content"><p class="stock-dashboard-loading">Carregando indicadores…</p></div>
    </section>
</div>

<script>
const canManageStockCategories = @json($canManageStockCategories);
const canManageStockItems = @json($canManageStockItems);
const canCreateStockEntry = @json($canCreateStockEntry);
const canCreateStockOutput = @json($canCreateStockOutput);
const canCancelStockMovements = @json($canCancelStockMovement);
const canViewStockCosts = @json($canViewStockCosts);
const canViewStockAuditDetails = @can('viewAuditLogs') true @else false @endcan;
const stockDashboardUrl = @json(route('stock.dashboard'));
let lastOpenedItemId = null;

function stockDashboardEscape(value) { return String(value ?? '-').replace(/[&<>\"]/g, char => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;' })[char]); }
function stockDashboardNumber(value) { return Number(value || 0).toLocaleString('pt-BR', { maximumFractionDigits: 2 }); }
function stockDashboardMoney(value) { return Number(value || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }); }
function stockDashboardDate(value) { return value ? new Date(`${value}T00:00:00`).toLocaleDateString('pt-BR') : 'Sem movimentação'; }
function stockDashboardDays(value) { if (value === null || value === undefined) return 'Nunca movimentado'; const days = Math.floor(Number(value)); return `${days} ${days === 1 ? 'dia' : 'dias'}`; }
function stockDashboardFinancialChart(rows) {
    const width = 760, height = 240, left = 48, right = 18, top = 18, bottom = 34;
    const max = Math.max(1, ...rows.flatMap(row => [Number(row.entries_value || 0), Number(row.outputs_value || 0)]));
    const x = index => rows.length === 1 ? (left + width - right) / 2 : left + index * ((width - left - right) / (rows.length - 1));
    const y = value => top + (height - top - bottom) * (1 - Number(value || 0) / max);
    const path = key => rows.map((row, index) => `${index ? 'L' : 'M'} ${x(index).toFixed(1)} ${y(row[key]).toFixed(1)}`).join(' ');
    const step = Math.max(1, Math.ceil(rows.length / 6));
    const grid = [0, .5, 1].map(ratio => { const value = max * (1 - ratio), position = top + (height - top - bottom) * ratio; return `<line x1="${left}" y1="${position}" x2="${width-right}" y2="${position}"/><text x="${left - 7}" y="${position + 3}" text-anchor="end">${stockDashboardMoney(value)}</text>`; }).join('');
    const labels = rows.map((row, index) => index % step === 0 || index === rows.length - 1 ? `<text x="${x(index)}" y="${height - 10}" text-anchor="middle">${stockDashboardEscape(row.label)}</text>` : '').join('');
    const points = (key, color, label) => rows.map((row, index) => `<circle cx="${x(index)}" cy="${y(row[key])}" r="3.5" fill="${color}"><title>${stockDashboardEscape(row.label)} — ${label}: ${stockDashboardMoney(row[key])}</title></circle>`).join('');
    const entriesTotal = rows.reduce((total, row) => total + Number(row.entries_value || 0), 0);
    const outputsTotal = rows.reduce((total, row) => total + Number(row.outputs_value || 0), 0);
    return `<div class="stock-dashboard-financial-chart"><svg viewBox="0 0 ${width} ${height}" role="img" aria-label="Entradas e saídas financeiras"><g class="stock-dashboard-financial-grid">${grid}${labels}</g><path class="stock-dashboard-financial-line entry" d="${path('entries_value')}"/>${points('entries_value', '#4f9ddd', 'Entradas')}<path class="stock-dashboard-financial-line output" d="${path('outputs_value')}"/>${points('outputs_value', '#dd7b6e', 'Saídas')}</svg><div class="stock-dashboard-financial-summary"><span><i></i> Entradas: <b>${stockDashboardMoney(entriesTotal)}</b></span><span><i class="out"></i> Saídas: <b>${stockDashboardMoney(outputsTotal)}</b></span></div></div>`;
}
function stockDashboardList(title, rows, columns, empty = 'Nenhum dado para o período selecionado.') {
    const head = columns.map(column => `<th>${column.label}</th>`).join('');
    const body = rows.length ? rows.map(row => `<tr>${columns.map(column => `<td>${column.render ? column.render(row) : stockDashboardEscape(row[column.key])}</td>`).join('')}</tr>`).join('') : `<tr><td colspan="${columns.length}" class="stock-dashboard-empty">${empty}</td></tr>`;
    return `<article class="stock-dashboard-card stock-dashboard-table-card"><h3>${title}</h3><div class="stock-dashboard-table-wrap"><table><thead><tr>${head}</tr></thead><tbody>${body}</tbody></table></div></article>`;
}
function renderStockDashboard(data) {
    const s = data.summary;
    const cost = s.can_view_costs;
    const kpis = [['Itens cadastrados', s.total_items, 'Total de itens ativos'], ['Abaixo do mínimo', s.below_minimum, 'Reposição necessária'], ['Itens zerados', s.zero_stock, 'Sem saldo disponível'], ['Entradas no período', stockDashboardNumber(s.entries), 'Movimentos válidos'], ['Saídas no período', stockDashboardNumber(s.outputs), 'Consumo/retirada'], ['Consumo em manutenção', stockDashboardNumber(s.maintenance_consumption), 'Saídas vinculadas a OMs'], ['Valor em estoque', cost ? stockDashboardMoney(s.stock_value) : 'Restrito', 'Permissão de custos'], ['Valor movimentado', cost ? stockDashboardMoney(s.movement_value) : 'Restrito', 'Permissão de custos']];
    const financialMovements = data.financial_movements || [];
    const hasFinancialMovements = financialMovements.some(row => Number(row.entries_value || 0) > 0 || Number(row.outputs_value || 0) > 0);
    let html = `<div class="stock-dashboard-kpis">${kpis.map(k => `<article><span>${k[0]}</span><strong>${k[1]}</strong><small>${k[2]}</small></article>`).join('')}</div>`;
    html += `<article class="stock-dashboard-card stock-dashboard-financial-card"><h3>Movimentação financeira do estoque</h3><p class="stock-dashboard-card-description">Valores estimados de entradas e saídas no período selecionado.</p>${!cost ? `<p class="stock-dashboard-empty">Restrito pela permissão de custos.</p>` : hasFinancialMovements ? stockDashboardFinancialChart(financialMovements) : `<p class="stock-dashboard-empty">Nenhuma movimentação financeira válida no período selecionado.</p>`}</article>`;
    html += `<div class="stock-dashboard-grid">`;
    html += stockDashboardList('Itens mais consumidos', data.top_consumed_items, [{label:'Item',key:'item'}, {label:'Categoria',key:'category'}, {label:'Quantidade',render:r=>`${stockDashboardNumber(r.quantity)} ${stockDashboardEscape(r.unit)}`}]);
    html += stockDashboardList('Categorias com maior consumo', data.top_categories, [{label:'Categoria',key:'category'}, {label:'Quantidade',render:r=>stockDashboardNumber(r.quantity)}, ...(cost ? [{label:'Valor',render:r=>stockDashboardMoney(r.value)}] : [])]);
    html += stockDashboardList('Itens abaixo do mínimo', data.below_minimum_items, [{label:'Item',key:'item'}, {label:'Saldo',render:r=>stockDashboardNumber(r.quantity)}, {label:'Mínimo',render:r=>stockDashboardNumber(r.minimum_quantity)}, {label:'Falta',render:r=>stockDashboardNumber(r.difference)}]);
    html += stockDashboardList('Itens zerados', data.zero_stock_items, [{label:'Item',key:'item'}, {label:'Categoria',key:'category'}, {label:'Última movimentação',render:r=>stockDashboardDate(r.last_movement)}]);
    html += stockDashboardList('Itens sem movimentação recente', data.stale_items, [{label:'Item',key:'item'}, {label:'Saldo',render:r=>stockDashboardNumber(r.quantity)}, {label:'Última movimentação',render:r=>stockDashboardDate(r.last_movement)}, {label:'Dias sem movimento',render:r=>stockDashboardDays(r.days_without_movement)}]);
    if (cost) html += stockDashboardList('Top itens por valor em estoque', data.top_stock_value_items, [{label:'Item',key:'item'}, {label:'Saldo',render:r=>stockDashboardNumber(r.quantity)}, {label:'Custo médio',render:r=>stockDashboardMoney(r.unit_cost)}, {label:'Valor estimado',render:r=>stockDashboardMoney(r.value)}]);
    html += stockDashboardList('Saídas por manutenção/procedimento', data.maintenance_outputs, [{label:'Procedimento',key:'procedure'}, {label:'Item',key:'item'}, {label:'Quantidade',render:r=>`${stockDashboardNumber(r.quantity)} ${stockDashboardEscape(r.unit)}`}, {label:'Veículo/OM',key:'vehicle'}], 'Não há saídas de estoque vinculadas a manutenções no período.');
    document.getElementById('stockDashboardContent').innerHTML = html + '</div>';
}
async function loadStockDashboard() {
    const content = document.getElementById('stockDashboardContent');
    content.innerHTML = '<p class="stock-dashboard-loading">Atualizando indicadores…</p>';
    try { const response = await fetch(`${stockDashboardUrl}?period=${encodeURIComponent(document.getElementById('stockDashboardPeriod').value)}`, {headers:{Accept:'application/json'}}); if (!response.ok) throw new Error(); renderStockDashboard(await response.json()); if (window.lucide) lucide.createIcons(); } catch (error) { content.innerHTML = '<p class="stock-dashboard-empty">Não foi possível carregar o painel. Tente novamente.</p>'; }
}
window.openStockDashboard = function () { const modal = document.getElementById('stockDashboardModal'); modal.style.display = 'flex'; modal.setAttribute('aria-hidden', 'false'); loadStockDashboard(); };
window.closeStockDashboard = function () { const modal = document.getElementById('stockDashboardModal'); modal.style.display = 'none'; modal.setAttribute('aria-hidden', 'true'); };
document.addEventListener('keydown', event => { if (event.key === 'Escape') window.closeStockDashboard(); });
document.addEventListener('DOMContentLoaded', () => { document.getElementById('stockDashboardPeriod')?.addEventListener('change', loadStockDashboard); document.getElementById('stockDashboardModal')?.addEventListener('click', event => { if (event.target.id === 'stockDashboardModal') window.closeStockDashboard(); }); });





function closeMovementModal()

{

    document

        .getElementById('movementModal')

        .style.display = 'none';



    document

        .getElementById('editItemModal')

        .style.display = 'flex';

}

function enableItemEdit()
{
    if (!canManageStockItems) { return; }
    closeMovementDetailPanel();

    const modalCard = document.querySelector('#editItemModal .stock-edit-item-modal-card');
    if (modalCard) { modalCard.classList.add('is-editing'); }

    document

        .querySelector('.details-view-mode')

        .style.display = 'none';



    document

        .querySelector('.details-edit-mode')

        .style.display = 'block';



    document

        .getElementById('saveItemBtn')

        .style.display = 'flex';



    const editItemBtn = document.getElementById('editItemBtn');
    if (editItemBtn) { editItemBtn.style.display = 'none'; }

}



function disableItemEdit()

{
    const modalCard = document.querySelector('#editItemModal .stock-edit-item-modal-card');
    if (modalCard) { modalCard.classList.remove('is-editing'); }

    document

        .querySelector('.details-view-mode')

        .style.display = 'block';



    document

        .querySelector('.details-edit-mode')

        .style.display = 'none';



    document

        .getElementById('saveItemBtn')

        .style.display = 'none';



    const editItemBtn = document.getElementById('editItemBtn');
    if (editItemBtn) { editItemBtn.style.display = 'inline-flex'; }

}



/* =========================

   ITEM MODAL

========================= */



function openItemModal(categoryId, categoryName)
{
    if (!canManageStockItems) { return; }
document

        .getElementById('itemModal')

        .style.display = 'flex';



    document

        .getElementById('stock_category_id')

        .value = categoryId;



    document

        .getElementById('itemCategoryName')

        .innerText = categoryName;





}



function closeItemModal()

{

    document

        .getElementById('itemModal')

        .style.display = 'none';

}



function openCategoryModal()
{
    if (!canManageStockCategories) { return; }

    document.getElementById('categoryForm').action = @json(route('stock.categories.store'));
    document.getElementById('categoryFormMethod').value = '';
    document.getElementById('categoryModalTitle').innerText = 'Nova categoria';
    document.getElementById('categorySubmitLabel').innerText = 'Salvar categoria';
    document.getElementById('categoryName').value = '';

    document

        .getElementById('categoryModal')

        .style.display = 'flex';

}



function closeCategoryModal()

{

    document

        .getElementById('categoryModal')

        .style.display = 'none';

}

function openCategoryEditModal(id, name)
{
    if (!canManageStockCategories) { return; }

    document.getElementById('categoryForm').action = @json(url('/stock/categories')) + '/' + id;
    document.getElementById('categoryFormMethod').value = 'PUT';
    document.getElementById('categoryModalTitle').innerText = 'Editar categoria';
    document.getElementById('categorySubmitLabel').innerText = 'Salvar alterações';
    document.getElementById('categoryName').value = name;
    document.getElementById('categoryModal').style.display = 'flex';
    if (window.lucide) lucide.createIcons();
}

function openCategoryDeleteModal(id, name)
{
    if (!canManageStockCategories) { return; }

    document.getElementById('categoryDeleteForm').action = @json(url('/stock/categories')) + '/' + id;
    document.getElementById('categoryDeleteMessage').innerText = 'Deseja excluir a categoria “' + name + '”? Esta ação não poderá ser desfeita.';
    document.getElementById('categoryDeleteModal').style.display = 'flex';
    if (window.lucide) lucide.createIcons();
}

function closeCategoryDeleteModal()
{
    document.getElementById('categoryDeleteModal').style.display = 'none';
}



let currentItemId = null;

async function openEditItemModal(id)

{

    currentItemId = id;

    const response =

        await fetch(`/stock/items/${id}/data`);



    const item =

        await response.json();



    document

        .getElementById('editItemForm')

        .action =

            `/stock/items/${item.id}`;

    const detailsLink = document.getElementById('stockItemDetailsLink');

    if (detailsLink) {
        detailsLink.href = `/stock/items/${item.id}`;
    }

    document

        .getElementById('editItemModal')

        .style.display = 'flex';



    /* =========================

       HEADER

    ========================= */



    document

        .getElementById('editItemName')

        .innerText =

            item.name;



    document

        .getElementById('editItemCategory')

        .innerText =

            item.category.name;



    /* =========================

       ESTOQUE

    ========================= */



    document

        .getElementById('editStockQuantity')

        .innerText =

            parseFloat(item.quantity)

            .toFixed(2);



    document

        .getElementById('editItemUnitBadge')

        .innerText =

            item.unit;



    /* =========================

       VIEW MODE

    ========================= */

    document

    .getElementById('viewItemBrand')

        .innerText =

            item.brand ?? '-';

    document

        .getElementById('viewItemUnit')

        .innerText =

            item.unit;



    document

        .getElementById('viewItemMinimum')

        .innerText =

            item.minimum_quantity;



    const viewItemCost = document.getElementById('viewItemCost');
    if (viewItemCost) {
        viewItemCost.innerText = item.unit_cost !== null ? 'R$ ' + parseFloat(item.unit_cost).toFixed(2) : 'Custo restrito';
    }



    document

        .getElementById('viewItemObservation')

        .innerText =

            item.observation ??

            'Sem observações';



    /* =========================

       EDIT MODE

    ========================= */



    document

        .getElementById('inputItemName')

        .value =

            item.name;



    document

        .getElementById('inputItemBrand')

        .value =

            item.brand ?? '';

    document

        .getElementById('inputItemUnit')

        .value =

            item.unit;



    document

        .getElementById('inputItemMinimum')

        .value =

            item.minimum_quantity;

    document.getElementById('inputItemWorkshopConsumable').checked = Boolean(item.is_workshop_consumable);



    const inputItemCost = document.getElementById('inputItemCost');
    if (inputItemCost) {
        inputItemCost.value = item.unit_cost ?? '';
    }



    document

        .getElementById('inputItemObservation')

        .value =

            item.observation ?? '';



    /* =========================

       MOVIMENTAÇÕES

    ========================= */



    let html = '';



    if (!item.movements || item.movements.length === 0) {



        html = `

            <div class="stock-empty-history">

                <i data-lucide="history"></i>

                <strong>Nenhuma movimentação</strong>

                <span>Entradas e saídas aparecerão aqui.</span>

            </div>

        `;



    } else {



        item.movements.forEach(movement => {
            const isCancelled = Boolean(movement.cancelled_at);
            const isReversal = Boolean(movement.reversed_from_movement_id);
            const isMaintenance = Boolean(movement.maintenance_record_id);
            const isReverted = Boolean(movement.reversal_movement_id);
            const canCancelMovement = canCancelStockMovements
                && !isCancelled
                && !isReversal
                && !isMaintenance;
            const rowClasses = [
                isCancelled ? 'is-cancelled' : '',
                isReversal ? 'is-reversal' : '',
                isMaintenance ? 'is-maintenance' : '',
            ].filter(Boolean).join(' ');
            const movementBadges = [
                isCancelled ? '<span class="stock-status-badge danger">Cancelada</span>' : '',
                isReversal ? '<span class="stock-status-badge warning">Reversão</span>' : '',
                isMaintenance ? '<span class="stock-status-badge info">Manutenção</span>' : '',
                isReverted && !isCancelled ? '<span class="stock-status-badge muted">Revertida</span>' : '',
            ].filter(Boolean).join('');
            const auditDetails = canViewStockAuditDetails && isCancelled && movement.cancel_reason
                ? `<small>Motivo: ${movement.cancel_reason}</small>`
                : '';
            const lockReason = !canCancelMovement
                ? (isCancelled
                    ? 'Movimento já cancelado.'
                    : (isReversal
                        ? 'Movimento reverso não pode ser cancelado diretamente.'
                        : (isMaintenance
                            ? 'Vinculado à manutenção; cancele pela manutenção.'
                            : 'Movimento não cancelável.')))
                : '';
            const shortDate = movement.moved_at
                ? new Date(movement.moved_at).toLocaleDateString('pt-BR')
                : (movement.created_at ? new Date(movement.created_at).toLocaleDateString('pt-BR') : '-');
            const movementIcon = isCancelled
                ? 'circle-x'
                : (isReversal ? 'rotate-ccw' : (movement.movement_type === 'in' ? 'arrow-down-left' : 'arrow-up-right'));
            const movementTitle = isReversal
                ? 'Reversão'
                : (movement.movement_type === 'in' ? 'Entrada' : 'Saída');
            const movementQuantity = Number(movement.quantity || 0).toLocaleString('pt-BR', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            });
            const lockNotice = lockReason
                ? `<small class="stock-movement-lock">${lockReason}</small>`
                : '';
            const cancelTrigger = canCancelMovement
                ? `<button type="button" class="stock-modal-cancel stock-movement-cancel-trigger" onclick="showMovementCancelForm(this)">Cancelar</button>`
                : '';
            const cancelPanel = canCancelMovement
                ? `
                    <form method="POST" action="/stock/movements/${movement.id}/cancel" class="stock-movement-cancel-form stock-movement-cancel-panel" style="display:none;">
                        <input type="hidden" name="_token" value="{{ csrf_token() }}">
                        <label>Motivo do cancelamento</label>
                        <textarea name="reason" rows="2" required minlength="5" placeholder="Descreva o motivo do cancelamento"></textarea>
                        <div class="stock-cancel-actions stock-movement-cancel-actions">
                            <button type="submit" class="stock-modal-cancel">Cancelar movimentação</button>
                            <button type="button" class="stock-cancel-dismiss" onclick="hideMovementCancelForm(this)">Fechar</button>
                        </div>
                    </form>
                `
                : '';

            html += `
                <article class="movement-row stock-movement-card ${rowClasses}">
                    <div class="stock-movement-card-body">
                        <div class="stock-movement-main">
                            <span class="movement-mini-icon stock-movement-icon ${movement.movement_type === 'in' ? 'is-in' : 'is-out'}">
                                <i data-lucide="${movementIcon}"></i>
                            </span>
                            <div class="stock-movement-content">
                                <div class="stock-movement-title">
                                    <strong>${movementTitle} <span>(${shortDate})</span></strong>
                                    <span class="stock-movement-badges">${movementBadges}</span>
                                </div>
                                <small class="movement-qty stock-movement-meta">Quantidade: ${movementQuantity} ${item.unit || ''}</small>
                            </div>
                        </div>

                        <div class="stock-cancel-box stock-movement-action-shell" data-cancel-box>
                            <div class="stock-movement-actions">
                                <button type="button" class="stock-movement-view-btn" onclick='showMovementDetails(${JSON.stringify(movement)})'>
                                    Ver informações
                                </button>
                                ${cancelTrigger}
                            </div>
                            ${cancelPanel}
                        </div>
                    </div>

                    ${movement.description ? `<small class="stock-movement-description">${movement.description}</small>` : ''}
                    ${auditDetails}
                    ${lockNotice}
                </article>
            `;
        });



    }



    document

        .getElementById('movementHistory')

        .innerHTML = html;



    disableItemEdit();

    if (window.lucide) {

        lucide.createIcons();

    }

}

async function openDirectEntry(id)
{
    await openEditItemModal(id);
    openMovementModal('in');
}





function closeEditItemModal()
{
    closeMovementDetailPanel();
    disableItemEdit();

    document
        .getElementById('editItemModal')
        .style.display = 'none';
}



function openMovementModal(type)
{
    if ((type === 'in' && !canCreateStockEntry) || (type === 'out' && !canCreateStockOutput)) { return; }

    lastOpenedItemId =

        currentItemId;



    document

        .getElementById('editItemModal')

        .style.display = 'none';



    const movementModal =

        document.getElementById('movementModal');



    const movementCard =

        movementModal.querySelector('.stock-movement-modal-card');



    movementCard.classList.remove(

        'is-in',

        'is-out'

    );



    movementCard.classList.add(

        type === 'in'

            ? 'is-in'

            : 'is-out'

    );



    movementModal.style.display = 'flex';

    document.getElementById('movementForm').reset();
    document.getElementById('movementMovedAt').value = new Date().toISOString().slice(0, 16);
    document.getElementById('movementType').value = type;
    document.getElementById('movementItemId').value = currentItemId;
    document.getElementById('movementMovedAt').value = localDateTimeValue();

    document

        .getElementById('movementType')

        .value = type;



    document

        .getElementById('movementItemId')

        .value = currentItemId;



    document

        .getElementById('movementModalItemName')

        .innerText =

            document

                .getElementById('editItemName')

                .innerText;



    if(type === 'in')

    {
        // document.querySelectorAll('.stock-entry-only').forEach(el => el.style.display = 'block');
        document.getElementById('movementDescriptionLabel').innerText = 'Observação';
        document.getElementById('movementDescription').required = false;
        document.getElementById('movementDescription').minLength = 0;
        document.getElementById('movementDescription').placeholder = 'Ex: Compra de item, ajuste de entrada, observação opcional...';
        document.getElementById('movementSubmitText').disabled = false;
        document

            .getElementById('movementModalTitle')

            .innerText =

                'Nova entrada';



        document

            .getElementById('movementSubmitText')

            .innerHTML =

                '<i data-lucide="check"></i> Confirmar entrada';

    }

    else

    {
        const counter = document.getElementById('movementDescriptionCounter');

        if (counter) {
            counter.style.display = 'none';
            counter.classList.remove('is-ok');
            counter.innerText = 'Informe pelo menos 10 caracteres.';
        }
        document.querySelectorAll('.stock-entry-only').forEach(el => el.style.display = 'none');
        document.getElementById('movementDescriptionLabel').innerText = 'Motivo da saída';
        document.getElementById('movementDescription').required = true;
        document.getElementById('movementDescription').minLength = 10;
        document.getElementById('movementDescription').placeholder = 'Informe obrigatoriamente o motivo da saída do estoque...';
        document.getElementById('movementSubmitText').disabled = true;
        document

            .getElementById('movementModalTitle')

            .innerText =

                'Nova saída';



        document

            .getElementById('movementSubmitText')

            .innerHTML =

                '<i data-lucide="check"></i> Confirmar saída';

    }



    if (window.lucide) {

        lucide.createIcons();

    }

}

document.getElementById('movementDescription').addEventListener('input', function () {
    const type = document.getElementById('movementType').value;
    const submit = document.getElementById('movementSubmitText');
    const counter = document.getElementById('movementDescriptionCounter');

    if (type !== 'out') {
        submit.disabled = false;

        if (counter) {
            counter.style.display = 'none';
        }

        return;
    }

    const length = this.value.trim().length;
    const missing = Math.max(0, 10 - length);

    submit.disabled = length < 10;

    if (! counter) {
        return;
    }

    if (length === 0) {
        counter.style.display = 'none';
        return;
    }

    counter.style.display = 'block';

    if (missing > 0) {
        counter.classList.remove('is-ok');
        counter.innerText = `Faltam ${missing} caractere(s) para aceitar o motivo.`;
    } else {
        counter.classList.add('is-ok');
        counter.innerText = '';
    }
});

function localDateTimeValue(date = new Date()) {
    const offset = date.getTimezoneOffset();
    const local = new Date(date.getTime() - offset * 60000);

    return local.toISOString().slice(0, 16);
}

function updateMovementUnitCost() {
    const quantity = Number(document.querySelector('#movementForm input[name="quantity"]').value || 0);
    const totalCost = Number(document.getElementById('movementTotalCost').value || 0);
    const unitCost = document.getElementById('movementUnitCost');

    if (!unitCost) return;

    const preview = document.getElementById('movementUnitCostPreview');

    if (quantity <= 0 || totalCost <= 0) {
        unitCost.value = '';
        if (preview) {
            preview.value = 'R$ 0,00';
        }
        return;
    }

    const calculated = totalCost / quantity;

    unitCost.value = calculated.toFixed(2);

    if (preview) {
        preview.value = calculated.toLocaleString('pt-BR', {
            style: 'currency',
            currency: 'BRL'
        });
    }
}

document
    .querySelector('#movementForm input[name="quantity"]')
    .addEventListener('input', updateMovementUnitCost);

document
    .getElementById('movementTotalCost')
    .addEventListener('input', updateMovementUnitCost);

function closeMovementModal()

{

    document

        .getElementById('movementModal')

        .style.display = 'none';



    document

        .getElementById('editItemModal')

        .style.display = 'flex';

}

function validateMovementSubmitMessage() {
    const type = document.getElementById('movementType').value;
    const description = document.getElementById('movementDescription');

    if (type === 'out' && description.value.trim().length < 10) {
        alert('Informe o motivo da saída com pelo menos 10 caracteres.');
        description.focus();
        return false;
    }

    return true;
}

function showMovementCancelForm(button) {
    const box = button.closest('[data-cancel-box]');
    const form = box.querySelector('.stock-movement-cancel-form');

    button.style.display = 'none';
    form.style.display = 'block';

    const textarea = form.querySelector('textarea');
    if (textarea) {
        textarea.focus();
    }
}

function hideMovementCancelForm(button) {
    const box = button.closest('[data-cancel-box]');
    const form = box.querySelector('.stock-movement-cancel-form');
    const trigger = box.querySelector('button[type="button"].stock-modal-cancel');

    form.reset();
    form.style.display = 'none';
    trigger.style.display = '';

}

function moneyBR(value) {
    const number = Number(value || 0);

    return number.toLocaleString('pt-BR', {
        style: 'currency',
        currency: 'BRL'
    });
}

function showMovementDetails(movement) {
    const panel = document.getElementById('movementDetailPanel');

    const typeLabel = movement.movement_type === 'in' ? 'Entrada' : 'Saída';

    const movementDate = movement.moved_at
        ? new Date(movement.moved_at).toLocaleString('pt-BR')
        : (
            movement.created_at
                ? new Date(movement.created_at).toLocaleString('pt-BR')
                : '-'
        );

    document.getElementById('movementDetailDate').innerText = movementDate;

    const icon = document.getElementById('movementDetailIcon');
    const isIn = movement.movement_type === 'in';

    icon.classList.remove('is-in', 'is-out');
    icon.classList.add(isIn ? 'is-in' : 'is-out');

    icon.innerHTML = isIn
        ? '<i data-lucide="arrow-down-left"></i>'
        : '<i data-lucide="arrow-up-right"></i>';

    let status = 'Ativa';

    if (movement.cancelled_at) {
        status = 'Cancelada';
    } else if (movement.reversed_from_movement_id) {
        status = 'Reversão';
    } else if (movement.reversal_movement_id) {
        status = 'Revertida';
    }
    document
        .querySelector('.stock-edit-content')
        .classList
        .add('is-viewing-movement');
    document.getElementById('movementDetailTitle').innerText = typeLabel;
    document.getElementById('movementDetailQuantity').innerText = movement.quantity ?? '-';
    const movementDetailUnitCost = document.getElementById('movementDetailUnitCost');
    if (movementDetailUnitCost) { movementDetailUnitCost.innerText = moneyBR(movement.unit_cost); }
    const movementDetailTotalCost = document.getElementById('movementDetailTotalCost');
    if (movementDetailTotalCost) { movementDetailTotalCost.innerText = moneyBR(movement.total_cost); }
    document.getElementById('movementDetailSupplier').innerText = movement.supplier_name || '-';
    document.getElementById('movementDetailInvoice').innerText = movement.invoice_number || '-';
    document.getElementById('movementDetailStatus').innerText = status;
    document.getElementById('movementDetailDescription').innerText = movement.description || '-';

    document.getElementById('movementDetailAudit').innerText =
        movement.cancelled_at
            ? `Cancelada. Motivo: ${movement.cancel_reason || 'não informado'}`
            : (
                movement.reversed_from_movement_id
                    ? `Movimento reverso da movimentação #${movement.reversed_from_movement_id}.`
                    : (
                        movement.reversal_movement_id
                            ? `Movimento revertido pela movimentação #${movement.reversal_movement_id}.`
                            : 'Sem cancelamento ou reversão.'
                    )
            );

    panel.style.display = 'block';

    if (window.lucide) {
        lucide.createIcons();
    }
}

function closeMovementDetailPanel() {
    const panel = document.getElementById('movementDetailPanel');

    if (panel) {
        panel.style.display = 'none';
    }

    const content = document.querySelector('.stock-edit-content');

    if (content) {
        content.classList.remove('is-viewing-movement');
    }
}

document.addEventListener('DOMContentLoaded', function () {
    const entryItemId = Number(@json(request()->query('entry')));

    if (entryItemId > 0 && canCreateStockEntry) {
        openDirectEntry(entryItemId);
    }
});
</script>





@include('fiscal-documents._import-modal')

@endsection
