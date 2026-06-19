@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/pages/fuel.css') }}?v=1">
@endpush

@section('content')
    <div class="fuel-page">
        <header class="fuel-header">
            <div>
                <span class="fuel-kicker">Abastecimentos</span>
                <h1>Tanques da unidade</h1>
                <p>
                    Controle as bases de combustível de {{ $activeLocation->name ?? 'unidade ativa' }}.
                    O saldo inicial é sempre zero e será movimentado por recebimentos.
                </p>
            </div>

            <a href="#fuel-tank-create" class="fuel-primary-action">
                <i data-lucide="plus"></i>
                Novo tanque
            </a>
        </header>

        <section class="fuel-summary-grid" aria-label="Resumo dos tanques">
            @forelse($tanks as $tank)
                <article class="fuel-tank-card {{ $tank->balance_status }}">
                    <div class="fuel-tank-card-top">
                        <div>
                            <span class="fuel-product-label">
                                {{ $tank->product?->name ?? 'Produto' }}
                            </span>
                            <h2>{{ $tank->name }}</h2>
                        </div>

                        <span class="fuel-status-badge {{ $tank->balance_status }}">
                            @if(! $tank->active)
                                Inativo
                            @elseif($tank->balance_status === 'low')
                                Saldo baixo
                            @else
                                Normal
                            @endif
                        </span>
                    </div>

                    <div class="fuel-balance-row">
                        <strong>
                            {{ number_format((float) $tank->current_balance_liters, 3, ',', '.') }} L
                        </strong>
                        <span>
                            de {{ number_format((float) $tank->capacity_liters, 3, ',', '.') }} L
                        </span>
                    </div>

                    <div class="fuel-progress-track">
                        <span style="width: {{ $tank->balance_percentage }}%"></span>
                    </div>

                    <dl class="fuel-tank-meta">
                        <div>
                            <dt>Saldo mínimo</dt>
                            <dd>{{ number_format((float) $tank->minimum_balance_liters, 3, ',', '.') }} L</dd>
                        </div>
                        <div>
                            <dt>Ocupação</dt>
                            <dd>{{ number_format((float) $tank->balance_percentage, 1, ',', '.') }}%</dd>
                        </div>
                    </dl>
                </article>
            @empty
                <article class="fuel-empty-card">
                    <i data-lucide="fuel"></i>
                    <h2>Nenhum tanque cadastrado</h2>
                    <p>Cadastre o primeiro tanque da unidade para iniciar o controle de abastecimentos.</p>
                </article>
            @endforelse
        </section>

        <section class="fuel-panel" id="fuel-tank-create">
            <div class="fuel-panel-header">
                <div>
                    <span class="fuel-kicker">Cadastro</span>
                    <h2>Novo tanque</h2>
                </div>
                <p>O saldo será iniciado em zero. Recebimentos entram em uma etapa futura.</p>
            </div>

            <form method="POST" action="{{ route('fuel.tanks.store') }}" class="fuel-form">
                @csrf

                <div class="fuel-form-grid">
                    <label>
                        Produto
                        <select name="fuel_product_id" required>
                            <option value="">Selecione</option>
                            @foreach($products as $product)
                                <option value="{{ $product->id }}" @selected(old('fuel_product_id') == $product->id)>
                                    {{ $product->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('fuel_product_id')
                            <small>{{ $message }}</small>
                        @enderror
                    </label>

                    <label>
                        Nome do tanque
                        <input
                            type="text"
                            name="name"
                            value="{{ old('name') }}"
                            required
                            maxlength="255"
                            placeholder="Ex.: Tanque Diesel 01"
                        >
                        @error('name')
                            <small>{{ $message }}</small>
                        @enderror
                    </label>

                    <label>
                        Capacidade em litros
                        <input
                            type="number"
                            name="capacity_liters"
                            value="{{ old('capacity_liters') }}"
                            min="0.001"
                            step="0.001"
                            required
                        >
                        @error('capacity_liters')
                            <small>{{ $message }}</small>
                        @enderror
                    </label>

                    <label>
                        Saldo mínimo
                        <input
                            type="number"
                            name="minimum_balance_liters"
                            value="{{ old('minimum_balance_liters', 0) }}"
                            min="0"
                            step="0.001"
                        >
                        @error('minimum_balance_liters')
                            <small>{{ $message }}</small>
                        @enderror
                    </label>
                </div>

                <label class="fuel-check">
                    <input type="checkbox" name="active" value="1" checked>
                    Tanque ativo
                </label>

                <div class="fuel-form-actions">
                    <button type="submit" class="fuel-primary-action">
                        <i data-lucide="save"></i>
                        Salvar tanque
                    </button>
                </div>
            </form>
        </section>

        <section class="fuel-panel">
            <div class="fuel-panel-header">
                <div>
                    <span class="fuel-kicker">Listagem</span>
                    <h2>Tanques cadastrados</h2>
                </div>
                <p>{{ $tanks->count() }} tanque(s) na unidade ativa.</p>
            </div>

            <div class="fuel-table-wrap">
                <table class="fuel-table">
                    <thead>
                        <tr>
                            <th>Tanque</th>
                            <th>Produto</th>
                            <th>Capacidade</th>
                            <th>Saldo atual</th>
                            <th>Saldo mínimo</th>
                            <th>Status</th>
                            <th>Editar</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($tanks as $tank)
                            <tr>
                                <td>
                                    <strong>{{ $tank->name }}</strong>
                                    <span>{{ $activeLocation->name }}</span>
                                </td>
                                <td>{{ $tank->product?->name ?? '-' }}</td>
                                <td>{{ number_format((float) $tank->capacity_liters, 3, ',', '.') }} L</td>
                                <td>{{ number_format((float) $tank->current_balance_liters, 3, ',', '.') }} L</td>
                                <td>{{ number_format((float) $tank->minimum_balance_liters, 3, ',', '.') }} L</td>
                                <td>
                                    <span class="fuel-status-badge {{ $tank->balance_status }}">
                                        @if(! $tank->active)
                                            Inativo
                                        @elseif($tank->balance_status === 'low')
                                            Saldo baixo
                                        @else
                                            Normal
                                        @endif
                                    </span>
                                </td>
                                <td>
                                    <details class="fuel-edit-details">
                                        <summary>Editar</summary>
                                        <form method="POST" action="{{ route('fuel.tanks.update', $tank) }}" class="fuel-edit-form">
                                            @csrf
                                            @method('PUT')

                                            <label>
                                                Produto
                                                <select name="fuel_product_id" required>
                                                    @foreach($products as $product)
                                                        <option value="{{ $product->id }}" @selected((int) $tank->fuel_product_id === (int) $product->id)>
                                                            {{ $product->name }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </label>

                                            <label>
                                                Nome
                                                <input type="text" name="name" value="{{ $tank->name }}" required maxlength="255">
                                            </label>

                                            <label>
                                                Capacidade
                                                <input type="number" name="capacity_liters" value="{{ $tank->capacity_liters }}" min="0.001" step="0.001" required>
                                            </label>

                                            <label>
                                                Saldo mínimo
                                                <input type="number" name="minimum_balance_liters" value="{{ $tank->minimum_balance_liters }}" min="0" step="0.001">
                                            </label>

                                            <label class="fuel-check">
                                                <input type="checkbox" name="active" value="1" @checked($tank->active)>
                                                Ativo
                                            </label>

                                            <button type="submit" class="fuel-secondary-action">
                                                Atualizar
                                            </button>
                                        </form>
                                    </details>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="fuel-table-empty">
                                    Nenhum tanque cadastrado para esta unidade.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection
