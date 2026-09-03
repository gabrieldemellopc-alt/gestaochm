@extends('layouts.app')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/pages/suppliers.css') }}?v=1">
@endpush

@section('content')
<div class="suppliers-page" x-data="supplierAdmin()">
    <header class="suppliers-header">
        <div><span>Gestão administrativa</span><h1>Fornecedores (CNPJ)</h1><p>Cadastro central de fornecedores e prestadores utilizados no CHM.</p></div>
        <button type="button" class="suppliers-primary-button" @click="openCreate()"><i class="bi bi-plus-lg"></i> Novo fornecedor</button>
    </header>

    @if(session('success'))<div class="suppliers-alert success"><i class="bi bi-check-circle"></i>{{ session('success') }}</div>@endif
    @if($errors->any())<div class="suppliers-alert warning"><i class="bi bi-exclamation-triangle"></i>{{ $errors->first() }}</div>@endif

    <form method="get" class="suppliers-search"><i class="bi bi-search"></i><input name="q" value="{{ request('q') }}" placeholder="Buscar por nome, alias ou CPF/CNPJ...">@if(request('q'))<a href="{{ route('suppliers.index') }}" aria-label="Limpar busca"><i class="bi bi-x-lg"></i> Limpar</a>@endif</form>

    <section class="suppliers-surface">
        <div class="suppliers-table-wrap"><table class="suppliers-table"><thead><tr><th>Fornecedor</th><th>CPF/CNPJ</th><th>Aliases</th><th>Status</th><th>Ações</th></tr></thead><tbody>
        @forelse($items as $supplier)<tr>
            <td><strong>{{ $supplier->displayName() }}</strong>@if($supplier->legal_name && $supplier->legal_name !== $supplier->displayName())<small>{{ $supplier->legal_name }}</small>@endif</td>
            <td>{{ $supplier->formattedDocument() ?: '—' }}</td>
            <td><div class="suppliers-aliases">
                @if($supplier->aliases->isNotEmpty())
                    @foreach($supplier->aliases->take(3) as $alias)<span>{{ $alias->alias }}</span>@endforeach
                    @if($supplier->aliases->count() > 3)<small>+{{ $supplier->aliases->count() - 3 }}</small>@endif
                @else
                    <small>—</small>
                @endif
            </div></td>
            <td><span class="suppliers-status {{ $supplier->active ? 'active' : 'inactive' }}">{{ $supplier->active ? 'Ativo' : 'Inativo' }}</span></td>
            <td><div class="suppliers-actions"><button type="button" @click='openEdit(@js(["id"=>$supplier->id,"trade_name"=>$supplier->trade_name,"legal_name"=>$supplier->legal_name,"document"=>$supplier->formattedDocument(),"active"=>(bool)$supplier->active]))'><i class="bi bi-pencil"></i> Editar</button><button type="button" @click='toggle(@js(["id"=>$supplier->id,"trade_name"=>$supplier->trade_name,"legal_name"=>$supplier->legal_name,"document"=>$supplier->formattedDocument(),"active"=>(bool)$supplier->active,"name"=>$supplier->displayName()]))'><i class="bi {{ $supplier->active ? 'bi-toggle-on' : 'bi-toggle-off' }}"></i> {{ $supplier->active ? 'Desativar' : 'Ativar' }}</button></div></td>
        </tr>@empty
            <tr><td colspan="5"><div class="suppliers-empty"><i class="bi bi-buildings"></i><strong>Nenhum fornecedor cadastrado</strong><p>Cadastre o primeiro fornecedor para começar a reutilizar CNPJ e nomes nos lançamentos.</p><button type="button" class="suppliers-primary-button" @click="openCreate()"><i class="bi bi-plus-lg"></i> Novo fornecedor</button></div></td></tr>
        @endforelse
        </tbody></table></div>
        <div class="suppliers-pagination">{{ $items->links() }}</div>
    </section>

    <div class="suppliers-modal-backdrop" x-show="modal.open" x-cloak @click.self="close()"><section class="suppliers-modal" role="dialog" aria-modal="true"><header><div><span x-text="modal.editing ? 'Editar cadastro' : 'Novo cadastro'"></span><h2 x-text="modal.editing ? 'Editar fornecedor' : 'Novo fornecedor'"></h2></div><button type="button" @click="close()" aria-label="Fechar"><i class="bi bi-x-lg"></i></button></header>
        <form method="post" :action="modal.action"><template x-if="modal.editing"><input type="hidden" name="_method" value="PUT"></template>@csrf
            <label>Nome fantasia / principal *<input name="trade_name" x-model="modal.trade_name" placeholder="Casa da Borracharia" required></label>
            <label>Razão social<input name="legal_name" x-model="modal.legal_name" placeholder="Casa da Borracharia Ltda"></label>
            <label>CPF/CNPJ<input name="document" x-model="modal.document" @input="formatDocument()" placeholder="00.000.000/0000-00" inputmode="numeric"></label>
            <label>Aliases<input name="aliases[]" placeholder="Casa Borracharia, Casa da Borracha"><small>Nomes alternativos usados para localizar este fornecedor.</small></label>
            <label class="suppliers-checkbox"><input type="hidden" name="active" value="0"><input type="checkbox" name="active" value="1" x-model="modal.active"> Fornecedor ativo</label>
            <footer><button type="button" class="suppliers-secondary-button" @click="close()">Cancelar</button><button class="suppliers-primary-button" x-text="modal.editing ? 'Salvar alterações' : 'Cadastrar fornecedor'"></button></footer>
        </form>
    </section></div>

    <form x-ref="toggleForm" method="post" :action="modal.action">@csrf @method('PUT')<input type="hidden" name="trade_name" x-model="modal.trade_name"><input type="hidden" name="legal_name" x-model="modal.legal_name"><input type="hidden" name="document" x-model="modal.document"><input type="hidden" name="active" x-model="modal.active"></form>
</div>
<script>function supplierAdmin(){return{modal:{open:false,editing:false,action:'',trade_name:'',legal_name:'',document:'',active:true},openCreate(){this.modal={open:true,editing:false,action:@js(route('suppliers.store')),trade_name:'',legal_name:'',document:'',active:true}},openEdit(s){this.modal={open:true,editing:true,action:`{{ url('/suppliers') }}/${s.id}`,trade_name:s.trade_name||'',legal_name:s.legal_name||'',document:s.document||'',active:s.active}},close(){this.modal.open=false},formatDocument(){let d=this.modal.document.replace(/\D/g,'').slice(0,14);this.modal.document=d.length<=11?d.replace(/(\d{3})(\d)/,'$1.$2').replace(/(\d{3})(\d)/,'$1.$2').replace(/(\d{3})(\d{1,2})$/,'$1-$2'):d.replace(/^(\d{2})(\d)/,'$1.$2').replace(/^(\d{2})\.(\d{3})(\d)/,'$1.$2.$3').replace(/\.(\d{3})(\d)/,'.$1/$2').replace(/(\d{4})(\d)/,'$1-$2')},toggle(s){let active=!s.active;if(!confirm(`${active?'Ativar':'Desativar'} fornecedor ${s.name}?`))return;this.modal={open:false,editing:true,action:`{{ url('/suppliers') }}/${s.id}`,trade_name:s.trade_name||'',legal_name:s.legal_name||'',document:s.document||'',active:active};this.$refs.toggleForm.submit()}}}</script>
@endsection
