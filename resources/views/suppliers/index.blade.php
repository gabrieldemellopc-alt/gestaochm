@extends('layouts.app')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/pages/suppliers.css') }}?v=1">
@endpush

@section('content')
<div class="suppliers-page" x-data="supplierAdmin()" x-effect="document.body.classList.toggle('suppliers-modal-open', modal.open)">
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

    <template x-teleport="body"><div class="suppliers-modal-backdrop" x-show="modal.open" x-cloak @click.self="close()"><section class="suppliers-modal" role="dialog" aria-modal="true"><header><div><span x-text="modal.editing ? 'Editar cadastro' : 'Novo cadastro'"></span><h2 x-text="modal.editing ? 'Editar fornecedor' : 'Novo fornecedor'"></h2></div><button type="button" @click="close()" aria-label="Fechar"><i class="bi bi-x-lg"></i></button></header>
        <form method="post" :action="modal.action" @submit="validateDocumentBeforeSubmit($event)"><template x-if="modal.editing"><input type="hidden" name="_method" value="PUT"></template>@csrf
            <input type="hidden" name="supplier_modal_mode" :value="modal.editing ? 'edit' : 'create'">
            <input type="hidden" name="supplier_id" :value="modal.id">
            <label>Nome fantasia / principal *<input name="trade_name" x-model="modal.trade_name" placeholder="Casa da Borracharia" required></label>
            <label>Razão social<input name="legal_name" x-model="modal.legal_name" placeholder="Casa da Borracharia Ltda"></label>
            <label>CPF/CNPJ<input name="document" x-model="modal.document" @input="formatDocument(); validateDocument()" @blur="validateDocument(true)" :class="{ 'is-invalid': documentError }" :aria-invalid="documentError ? 'true' : 'false'" placeholder="00.000.000/0000-00" inputmode="numeric"><small class="suppliers-field-error" x-show="documentError" x-text="documentError"></small></label>
            <label>Aliases<input name="aliases[]" value="{{ old('aliases.0') }}" placeholder="Casa Borracharia, Casa da Borracha"><small>Nomes alternativos usados para localizar este fornecedor.</small></label>
            <label class="suppliers-checkbox"><input type="hidden" name="active" value="0"><input type="checkbox" name="active" value="1" x-model="modal.active"> Fornecedor ativo</label>
            <footer><button type="button" class="suppliers-secondary-button" @click="close()">Cancelar</button><button class="suppliers-primary-button" x-text="modal.editing ? 'Salvar alterações' : 'Cadastrar fornecedor'"></button></footer>
        </form>
    </section></div></template>

    <form x-ref="toggleForm" method="post" :action="modal.action">@csrf @method('PUT')<input type="hidden" name="trade_name" x-model="modal.trade_name"><input type="hidden" name="legal_name" x-model="modal.legal_name"><input type="hidden" name="document" x-model="modal.document"><input type="hidden" name="active" x-model="modal.active"></form>
</div>
<script>function supplierAdmin(){const restoring=@js($errors->any());const editing=@js(old('supplier_modal_mode') === 'edit');const id=@js(old('supplier_id'));return{modal:{open:restoring,editing:editing,id:id,action:editing&&id?`{{ url('/suppliers') }}/${id}`:@js(route('suppliers.store')),trade_name:@js(old('trade_name')),legal_name:@js(old('legal_name')),document:@js(old('document')),active:@js((bool) old('active', true))},documentError:@js($errors->first('document')),openCreate(){this.documentError='';this.modal={open:true,editing:false,id:null,action:@js(route('suppliers.store')),trade_name:'',legal_name:'',document:'',active:true}},openEdit(s){this.documentError='';this.modal={open:true,editing:true,id:s.id,action:`{{ url('/suppliers') }}/${s.id}`,trade_name:s.trade_name||'',legal_name:s.legal_name||'',document:s.document||'',active:s.active}},close(){this.modal.open=false},formatDocument(){let d=this.modal.document.replace(/\D/g,'').slice(0,14);this.modal.document=d.length<=11?d.replace(/(\d{3})(\d)/,'$1.$2').replace(/(\d{3})(\d)/,'$1.$2').replace(/(\d{3})(\d{1,2})$/,'$1-$2'):d.replace(/^(\d{2})(\d)/,'$1.$2').replace(/^(\d{2})\.(\d{3})(\d)/,'$1.$2.$3').replace(/\.(\d{3})(\d)/,'.$1/$2').replace(/(\d{4})(\d)/,'$1-$2')},validCpf(v){if(!/^\d{11}$/.test(v)||/^(\d)\1+$/.test(v))return false;for(let p=9;p<11;p++){let sum=0;for(let i=0;i<p;i++)sum+=Number(v[i])*(p+1-i);if(((sum*10)%11)%10!==Number(v[p]))return false}return true},validCnpj(v){if(!/^\d{14}$/.test(v)||/^(\d)\1+$/.test(v))return false;for(const p of [12,13]){let sum=0,weight=p===12?5:6;for(let i=0;i<p;i++){sum+=Number(v[i])*weight;if(--weight<2)weight=9}const digit=sum%11<2?0:11-(sum%11);if(digit!==Number(v[p]))return false}return true},validateDocument(onSubmit=false){const d=this.modal.document.replace(/\D/g,'');if(!d){this.documentError='';return true}if(d.length===11||d.length===14){const valid=d.length===11?this.validCpf(d):this.validCnpj(d);this.documentError=valid?'':'Informe um CPF ou CNPJ válido.';return valid}this.documentError=onSubmit?'Informe um CPF ou CNPJ válido.':'';return !onSubmit},validateDocumentBeforeSubmit(event){if(!this.validateDocument(true))event.preventDefault()},toggle(s){let active=!s.active;if(!confirm(`${active?'Ativar':'Desativar'} fornecedor ${s.name}?`))return;this.modal={open:false,editing:true,id:s.id,action:`{{ url('/suppliers') }}/${s.id}`,trade_name:s.trade_name||'',legal_name:s.legal_name||'',document:s.document||'',active:active};this.$refs.toggleForm.submit()}}}</script>
@endsection
