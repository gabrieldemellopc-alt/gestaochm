@extends('layouts.app')

@push('styles')
<link
    rel="stylesheet"
    href="{{ asset('css/pages/stock.css') }}"
>
@endpush

@section('content')
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
    
            <button
                type="button"
                class="chm-page-button primary"
                onclick="openCategoryModal()"
            >
                <i data-lucide="plus"></i>
                Nova categoria
            </button>
    
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
                                {{ $category->items->count() }}
                                item(ns) cadastrado(s)
                            </span>

                        </div>

                    </div>

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

                            </div>

                            <div class="stock-item-values">

                                <div>

                                    <span>
                                        Estoque
                                    </span>

                                    <strong>
                                        {{ number_format($item->quantity, 2, ',', '.') }}
                                        {{ $item->unit }}
                                    </strong>

                                </div>

                                <div>

                                    <span>
                                        Mínimo
                                    </span>

                                    <strong>
                                        {{ number_format($item->minimum_quantity, 2, ',', '.') }}
                                    </strong>

                                </div>

                            </div>

                            <div class="stock-item-footer">

                                @if($item->stock_status === 'danger')

                                    <span class="stock-status-badge danger">
                                        <i data-lucide="circle-alert"></i>
                                        Crítico
                                    </span>

                                @elseif($item->stock_status === 'warning')

                                    <span class="stock-status-badge warning">
                                        <i data-lucide="triangle-alert"></i>
                                        Atenção
                                    </span>

                                @else

                                    <span class="stock-status-badge ok">
                                        <i data-lucide="check-circle"></i>
                                        Adequado
                                    </span>

                                @endif

                                <span class="stock-details-link">
                                    Ver detalhes
                                    <i data-lucide="chevron-right"></i>
                                </span>

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

                <button
                    type="button"
                    class="chm-page-button primary"
                    onclick="openCategoryModal()"
                >
                    <i data-lucide="plus"></i>

                    Criar categoria
                </button>

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

                <h2>
                    Nova categoria
                </h2>

                <p>
                    Organize os itens do estoque por tipo, finalidade ou setor.
                </p>

            </div>

        </div>

        <form
            method="POST"
            action="{{ route('stock.categories.store') }}"
            class="stock-modal-form"
        >

            @csrf

            <div class="form-group">

                <label>
                    Nome da categoria
                </label>

                <input
                    type="text"
                    name="name"
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

                    Salvar categoria
                </button>

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

            <div class="stock-item-modal-grid">

                <div class="form-group">

                    <label>
                        Nome do item
                    </label>

                    <input
                        type="text"
                        name="name"
                        class="form-input"
                        placeholder="Ex: Filtro de combustível"
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
                        class="form-input"
                        placeholder="Ex: Shell"
                    >

                </div>

                <div class="form-group">

                    <label>
                        Unidade
                    </label>

                    <select
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
                        Estoque inicial
                    </label>

                    <input
                        type="number"
                        step="0.01"
                        min="0"
                        name="quantity"
                        class="form-input"
                        value="0"
                    >

                </div>

                <div class="form-group">

                    <label>
                        Estoque mínimo
                    </label>

                    <input
                        type="number"
                        step="0.01"
                        min="0"
                        name="minimum_quantity"
                        class="form-input"
                        value="0"
                    >

                </div>

                <div class="form-group">

                    <label>
                        Custo unitário
                    </label>

                    <input
                        type="number"
                        step="0.01"
                        min="0"
                        name="unit_cost"
                        class="form-input"
                        value="0"
                    >

                </div>

                <div class="form-group full-width">

                    <label>
                        Observação
                    </label>

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

        <button
            type="button"
            onclick="closeEditItemModal()"
            class="stock-modal-close"
        >
            <i data-lucide="x"></i>
        </button>

        <div class="stock-edit-modal-layout">

            {{-- ESQUERDA --}}
            <aside class="stock-edit-sidebar">

                <div class="stock-balance-card-new">

                    <div class="stock-balance-icon">
                        <i data-lucide="package-check"></i>
                    </div>

                    <span>
                        Estoque atual
                    </span>

                    <h2 id="editStockQuantity">
                        0
                    </h2>

                    <small id="editItemUnitBadge">
                        Unidade
                    </small>

                </div>

                <div class="stock-movement-actions-new">

                    <button
                        type="button"
                        class="stock-movement-btn in"
                        onclick="openMovementModal('in')"
                    >
                        <i data-lucide="plus"></i>

                        Entrada
                    </button>

                    <button
                        type="button"
                        class="stock-movement-btn out"
                        onclick="openMovementModal('out')"
                    >
                        <i data-lucide="minus"></i>

                        Saída
                    </button>

                </div>

                <div class="stock-history-card-new">

                    <div class="stock-history-header-new">

                        <div>

                            <span>
                                Histórico
                            </span>

                            <h3>
                                Últimas movimentações
                            </h3>

                        </div>

                        <i data-lucide="history"></i>

                    </div>

                    <div
                        id="movementHistory"
                        class="stock-movement-history-list"
                    >
                    </div>

                </div>

            </aside>

            {{-- DIREITA --}}
            <section class="stock-edit-content">

                <div class="stock-edit-header-new">

                    <div class="stock-modal-icon">
                        <i data-lucide="package"></i>
                    </div>

                    <div>

                        <span id="editItemCategory">
                        </span>

                        <h2 id="editItemName">
                        </h2>

                        <p>
                            Detalhes, saldo e movimentações do item em estoque.
                        </p>

                    </div>

                </div>

                <div class="stock-edit-actions-top">

                    <button
                        type="button"
                        class="stock-edit-trigger-btn"
                        onclick="enableItemEdit()"
                        id="editItemBtn"
                    >
                        <i data-lucide="pencil"></i>

                        Editar item
                    </button>

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

                        <div class="stock-detail-card">

                            <span>
                                Custo unitário
                            </span>

                            <strong id="viewItemCost">
                            </strong>

                        </div>

                        <div class="stock-detail-card full-width">

                            <span>
                                Observações
                            </span>

                            <p id="viewItemObservation">
                            </p>

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
                            >

                        </div>

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

            <div class="stock-movement-form-grid">

                <div class="form-group">

                    <label>
                        Quantidade
                    </label>

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

                <div class="form-group full-width">

                    <label>
                        Observação
                    </label>

                    <textarea
                        name="description"
                        rows="4"
                        class="form-input"
                        placeholder="Ex: Compra de item, uso em manutenção, ajuste de estoque..."
                    ></textarea>

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
                >
                    <i data-lucide="check"></i>

                    Confirmar
                </button>

            </div>

        </form>

    </div>

</div>
<script>
let lastOpenedItemId = null;

function openMovementModal(type)
{
    lastOpenedItemId =
        currentItemId;

    document
        .getElementById('editItemModal')
        .style.display = 'none';

    document
        .getElementById('movementModal')
        .style.display = 'flex';

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
        document
            .getElementById('movementModalTitle')
            .innerText =
                'Nova entrada';

        document
            .getElementById('movementSubmitText')
            .innerText =
                'Confirmar entrada';
    }
    else
    {
        document
            .getElementById('movementModalTitle')
            .innerText =
                'Nova saída';

        document
            .getElementById('movementSubmitText')
            .innerText =
                'Confirmar saída';
    }
}

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
    document
        .querySelector('.details-view-mode')
        .style.display = 'none';

    document
        .querySelector('.details-edit-mode')
        .style.display = 'block';

    document
        .getElementById('saveItemBtn')
        .style.display = 'flex';

    document
        .getElementById('editItemBtn')
        .style.display = 'none';
}

function disableItemEdit()
{
    document
        .querySelector('.details-view-mode')
        .style.display = 'block';

    document
        .querySelector('.details-edit-mode')
        .style.display = 'none';

    document
        .getElementById('saveItemBtn')
        .style.display = 'none';

    document
        .getElementById('editItemBtn')
        .style.display = 'inline-flex';
}

/* =========================
   ITEM MODAL
========================= */

function openItemModal(
    categoryId,
    categoryName
){
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

let currentItemId = null;
async function openEditItemModal(id)
{
    currentItemId = id;
    const response =
        await fetch(`/stock/items/${id}`);

    const item =
        await response.json();

    document
        .getElementById('editItemForm')
        .action =
            `/stock/items/${item.id}`;
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

    document
        .getElementById('viewItemCost')
        .innerText =
            'R$ ' +
            parseFloat(item.unit_cost)
            .toFixed(2);

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

    document
        .getElementById('inputItemCost')
        .value =
            item.unit_cost;

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
    
            html += `
                <div class="movement-row">
    
                    <div class="movement-top">
    
                        <strong>
                            ${movement.movement_type === 'in'
                                ? 'Entrada'
                                : 'Saída'}
                        </strong>
    
                        <span>
                            ${movement.quantity}
                        </span>
    
                    </div>
    
                    <small>
                        ${movement.description ?? ''}
                    </small>
    
                </div>
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


function closeEditItemModal()
{
    document
        .getElementById('editItemModal')
        .style.display = 'none';
}

function openMovementModal(type)
{
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
function closeMovementModal()
{
    document
        .getElementById('movementModal')
        .style.display = 'none';

    document
        .getElementById('editItemModal')
        .style.display = 'flex';
}
</script>


@endsection
