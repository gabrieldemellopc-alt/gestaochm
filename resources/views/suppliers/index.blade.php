@extends('layouts.app')

@section('content')
<main class="container py-4">
    <header class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
        <div>
            <h1 class="mb-1">Fornecedores (CNPJ)</h1>
            <p class="text-muted mb-0">Cadastro central de fornecedores e prestadores utilizados no CHM.</p>
        </div>
        <a class="btn btn-primary" href="#novo-fornecedor"><i class="bi bi-plus-lg"></i> Novo fornecedor</a>
    </header>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

    <form method="get" class="card card-body mb-3">
        <div class="input-group"><input class="form-control" name="q" value="{{ request('q') }}" placeholder="Buscar por nome, alias ou CPF/CNPJ"><button class="btn btn-outline-secondary">Buscar</button></div>
    </form>

    <section id="novo-fornecedor" class="card mb-4">
        <div class="card-body"><h2 class="h5">Novo fornecedor</h2>
            <form method="post" action="{{ route('suppliers.store') }}" class="row g-3">@csrf
                <div class="col-md-4"><label class="form-label">Nome fantasia / principal</label><input class="form-control" name="trade_name" required></div>
                <div class="col-md-3"><label class="form-label">Razão social</label><input class="form-control" name="legal_name"></div>
                <div class="col-md-3"><label class="form-label">CPF/CNPJ</label><input class="form-control" name="document"></div>
                <div class="col-md-2"><label class="form-label">Alias</label><input class="form-control" name="aliases[]"></div>
                <div class="col-12 d-flex justify-content-between align-items-center"><label class="form-check"><input class="form-check-input" type="checkbox" name="active" value="1" checked> <span class="form-check-label">Ativo</span></label><button class="btn btn-primary">Cadastrar</button></div>
            </form>
        </div>
    </section>

    <section class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <thead><tr><th>Fornecedor</th><th>CPF/CNPJ</th><th>Aliases</th><th>Status</th><th class="text-end">Ações</th></tr></thead>
        <tbody>@forelse($items as $supplier)<tr>
            <td><strong>{{ $supplier->displayName() }}</strong>@if($supplier->legal_name && $supplier->legal_name !== $supplier->displayName())<small class="d-block text-muted">{{ $supplier->legal_name }}</small>@endif</td>
            <td>{{ $supplier->formattedDocument() ?: '—' }}</td>
            <td><small class="text-muted">{{ $supplier->aliases->pluck('alias')->join(' · ') ?: '—' }}</small></td>
            <td><span class="badge {{ $supplier->active ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $supplier->active ? 'Ativo' : 'Inativo' }}</span></td>
            <td class="text-end"><details class="d-inline-block text-start"><summary class="btn btn-sm btn-outline-secondary">Editar</summary><form method="post" action="{{ route('suppliers.update', $supplier) }}" class="card card-body mt-2" style="min-width:290px">@csrf @method('PUT')
                <input class="form-control form-control-sm mb-2" name="trade_name" value="{{ $supplier->trade_name }}" placeholder="Nome fantasia"><input class="form-control form-control-sm mb-2" name="legal_name" value="{{ $supplier->legal_name }}" placeholder="Razão social"><input class="form-control form-control-sm mb-2" name="document" value="{{ $supplier->formattedDocument() }}" placeholder="CPF/CNPJ"><input class="form-control form-control-sm mb-2" name="aliases[]" placeholder="Adicionar alias"><input type="hidden" name="active" value="0"><label class="form-check mb-2"><input class="form-check-input" type="checkbox" name="active" value="1" @checked($supplier->active)> Ativo</label><button class="btn btn-sm btn-primary">Salvar</button></form></details></td>
        </tr>@empty<tr><td colspan="5" class="text-center text-muted py-4">Nenhum fornecedor encontrado.</td></tr>@endforelse</tbody>
    </table></div></section>
    <div class="mt-3">{{ $items->links() }}</div>
</main>
@endsection
