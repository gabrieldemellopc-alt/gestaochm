<div class="fiscal-import-root" x-data="fiscalInvoiceImport()" x-effect="document.documentElement.classList.toggle('fiscal-import-open', open)" @open-fiscal-import.window="openUpload()" @keydown.escape.window="close()" x-cloak>
    <div class="fiscal-detail-backdrop" x-show="open" @click.self="close()">
        <article class="fiscal-detail-modal fiscal-import-modal">
            <div class="fiscal-detail-content">
                <button type="button" class="fiscal-detail-close" @click="close()">×</button>
                <template x-if="step === 'upload'">
                    <form @submit.prevent="parse()">
                        <span class="fiscal-kicker">Importação assistida</span><h2>Importar nota fiscal</h2>
                        <p>Envie o XML da NF-e para leitura automática dos itens. O PDF/DANFE pode ser usado como apoio, mas pode exigir conferência manual.</p>
                        <label class="fiscal-import-file"><span>Formatos aceitos: XML da NF-e ou PDF/DANFE (até 10 MB)</span><input type="file" accept=".xml,.pdf,application/xml,text/xml,application/pdf" @change="file=$event.target.files[0]" required><small x-text="file ? file.name : 'Nenhum arquivo selecionado'"></small></label>
                        <p class="fiscal-import-error" x-show="error" x-text="error"></p>
                        <footer class="fiscal-detail-actions"><button type="button" class="fiscal-button secondary" @click="close()">Cancelar</button><button class="fiscal-button primary" :disabled="busy" x-text="busy?'Lendo…':'Ler nota fiscal'"></button></footer>
                    </form>
                </template>
                <template x-if="step === 'review'">
                    <form @submit.prevent="confirm()">
                        <div class="fiscal-detail-header"><div><span class="fiscal-kicker">Validação humana obrigatória</span><h2>Conferir nota fiscal</h2></div><span class="fiscal-detail-location" x-text="(note.import_source==='xml'?'XML':'PDF')+' • '+note.items.length+' item(ns) lido(s)'"></span></div>
                        <div class="fiscal-alert" x-show="note.warning"><span x-text="note.warning"></span></div>
                        <div class="fiscal-import-grid">
                            <label>Número<input x-model="note.number" required></label><label>Série<input x-model="note.series"></label><label>Chave de acesso<input x-model="note.access_key" maxlength="44"></label>
                            <label>Emissão<input type="date" x-model="note.issued_at"></label><label>Fornecedor<input x-model="note.supplier_name" required></label><label>CNPJ<input x-model="note.supplier_cnpj"></label>
                            <label>Valor produtos<input type="number" min="0" step="0.01" x-model.number="note.products_total"></label><label>Valor da nota<input type="number" min="0" step="0.01" x-model.number="note.total_amount"></label>
                            <label>Destinatário<input x-model="note.recipient_name"></label><label>CNPJ destinatário<input x-model="note.recipient_cnpj"></label><label>Desconto<input type="number" min="0" step="0.01" x-model.number="note.discount_total"></label><label>Frete<input type="number" min="0" step="0.01" x-model.number="note.freight_total"></label>
                        </div>
                        <div class="fiscal-import-summary"><strong x-text="note.items.filter(i=>i.action!=='ignore').length+' importáveis'"></strong><span x-text="money(validatedTotal())"></span><span :class="Math.abs((note.total_amount||0)-validatedTotal())>.02?'has-difference':''" x-text="'Divergência: '+money((note.total_amount||0)-validatedTotal())"></span></div>
                        <div class="fiscal-import-items">
                            <div class="fiscal-import-empty" x-show="note.items.length===0">Nenhum item foi extraído. Use “Adicionar item manualmente” ou volte e envie o XML da NF-e.</div>
                            <template x-for="(item,index) in note.items" :key="index"><section class="fiscal-import-item" x-init="$nextTick(() => ensureCategorySelection(item))" :class="'status-'+item.action">
                                <div class="fiscal-import-item-head">
                                    <div><strong x-text="item.description||'Item sem descrição'"></strong><span class="fiscal-match-badge" :class="'match-'+(item.match_level||'none')" x-text="item.action==='ignore'?'Ignorado':(item.suggested_item_id?'Item encontrado':((item.suggestions||[]).length?'Possíveis correspondências':'Novo item provável'))"></span></div>
                                    <select x-model="item.action" @change="changeAction(item)"><option value="existing">Associar existente</option><option value="new">Criar novo item</option><option value="ignore">Ignorar</option></select>
                                </div>
                                <div class="fiscal-auto-suggestion" x-show="item.suggested_item_id && item.action === 'existing'">
                                    <span x-text="'Sugestão automática: ' + (((item.suggestions || []).find(suggestion => Number(suggestion.id) === Number(item.stock_item_id)) || {}).name || 'item encontrado')"></span>
                                    <button type="button" @click="item.action = 'new'; item.stock_item_id = null; item.suggested_item_id = null; restoreTextualCategory(item)">Limpar sugestão</button>
                                </div>
                                <div class="fiscal-match-suggestions" x-show="!item.suggested_item_id && (item.suggestions || []).length && item.action !== 'ignore'">
                                    <span>Possíveis itens já cadastrados</span>
                                    <div>
                                        <template x-for="suggestion in item.suggestions" :key="suggestion.id">
                                            <button type="button" @click="item.action = 'existing'; item.stock_item_id = suggestion.id; item.stock_category_id = suggestion.category_id ? String(suggestion.category_id) : ''; item.suggested_item_id = suggestion.id; item.match_level = suggestion.match_level">
                                                <strong x-text="suggestion.name"></strong>
                                                <small x-text="(suggestion.category_name || 'Sem categoria') + ' • ' + suggestion.unit + ' • ' + suggestion.reason"></small>
                                            </button>
                                        </template>
                                    </div>
                                </div>
                                <p class="fiscal-no-match" x-show="!(item.suggestions || []).length && item.action === 'new'">Nenhum item parecido encontrado. Revise a categoria antes de importar.</p>
                                <p class="fiscal-import-error" x-show="item.validation_error" x-text="item.validation_error"></p>`r`n                                <div class="fiscal-import-grid" x-show="item.action!=='ignore'">
                                    <label class="wide">Descrição<input x-model="item.description" required></label><label>Código<input x-model="item.product_code"></label><label>NCM<input x-model="item.ncm"></label><label>CFOP<input x-model="item.cfop"></label>
                                    <label x-show="item.action==='existing'">Item do estoque<select x-model.number="item.stock_item_id" @change="let selected=stockItems.find(stock=>Number(stock.id)===Number(item.stock_item_id));if(selected)item.stock_category_id=selected.stock_category_id ? String(selected.stock_category_id) : ''"><option value="">Selecione</option><template x-for="stock in stockItems"><option :value="stock.id" x-text="stock.name+' · '+stock.unit"></option></template></select></label>
                                    <label>Categoria<select x-model="item.stock_category_id" @change="item.category_manually_changed = true"><option value="">Selecione</option><template x-for="category in categories"><option :value="String(category.id)" x-text="category.name"></option></template></select><small x-show="item.action === 'new' && hasSuggestedCategory(item) && String(item.stock_category_id) === String(suggestedCategoryId(item))" x-text="'Sugestão aplicada por ' + item.textual_category_reason + '.'"></small></label>
                                    <label>Unidade<input x-model="item.unit" required></label><label>Quantidade<input type="number" min="0.0001" step="0.0001" x-model.number="item.quantity" required></label><label>Valor unitário<input type="number" min="0" step="0.0001" x-model.number="item.unit_value" required></label><label>Desconto<input type="number" min="0" step="0.01" x-model.number="item.discount_value"></label><label>Valor total<input type="number" min="0" step="0.01" x-model.number="item.total_value" required></label>
                                </div>
                            </section></template>
                            <button type="button" class="fiscal-button secondary" @click="addItem()">Adicionar item manualmente</button>
                        </div>
                        <p class="fiscal-import-error" x-show="error" x-text="error"></p>
                        <footer class="fiscal-detail-actions"><button type="button" class="fiscal-button secondary" @click="step='upload'">Voltar</button><button class="fiscal-button primary" :disabled="busy" x-text="busy?'Importando…':'Confirmar e lançar no estoque'"></button></footer>
                    </form>
                </template>
            </div>
        </article>
    </div>
</div>
<script>
function fiscalInvoiceImport(){return{open:false,step:'upload',busy:false,error:'',file:null,token:null,note:{items:[]},stockItems:[],categories:[],openUpload(){this.open=true;this.step='upload';this.error=''},close(){if(!this.busy)this.open=false},money(v){return Number(v||0).toLocaleString('pt-BR',{style:'currency',currency:'BRL'})},validatedTotal(){return this.note.items.filter(i=>i.action!=='ignore').reduce((s,i)=>s+Number(i.total_value||0),0)},suggestedCategoryId(item){return item.category_suggested_id||item.textual_suggested_category_id||null},hasSuggestedCategory(item){let categoryId=this.suggestedCategoryId(item);return categoryId&&this.categories.some(category=>String(category.id)===String(categoryId))},ensureCategorySelection(item,force=false){if(item.action==='new'&&!item.stock_item_id&&(force||!item.category_manually_changed)&&this.hasSuggestedCategory(item))item.stock_category_id=String(this.suggestedCategoryId(item))},restoreTextualCategory(item,force=false){this.ensureCategorySelection(item,force)},changeAction(item){if(item.action==='new'){item.stock_item_id=null;item.category_manually_changed=false;this.restoreTextualCategory(item,true)}},hydrateItems(items){return(items||[]).map(item=>{item.category_manually_changed=false;if(item.stock_item_id){item.stock_category_id=item.stock_category_id?String(item.stock_category_id):''}else{this.restoreTextualCategory(item,true)}return item})},applySuggestedCategories(){this.note.items.forEach(item=>this.ensureCategorySelection(item))},addItem(){this.note.items.push({action:'new',description:'',product_code:'',ncm:'',cfop:'',unit:'UN',quantity:1,unit_value:0,discount_value:0,total_value:0,stock_item_id:null,stock_category_id:null})},async parse(){if(!this.file)return;this.busy=true;this.error='';let f=new FormData();f.append('file',this.file);try{let r=await fetch(@json(route('fiscal-documents.import.parse')),{method:'POST',headers:{'X-CSRF-TOKEN':@json(csrf_token()),'Accept':'application/json'},body:f});let d=await r.json();if(!r.ok)throw new Error(d.message||Object.values(d.errors||{})[0]?.[0]);this.token=d.token;this.note=d.note;this.note.items=this.hydrateItems(this.note.items);let date=this.note.issued_at||'';this.note.issued_at=date.includes('/')?date.split('/').reverse().join('-'):date.substring(0,10);this.stockItems=d.stock_items;this.categories=d.categories;this.$nextTick(()=>this.applySuggestedCategories());this.step='review'}catch(e){this.error=e.message||'Não foi possível ler o arquivo.'}finally{this.busy=false}},async confirm(){this.busy=true;this.error='';try{let r=await fetch(@json(route('fiscal-documents.import.confirm')),{method:'POST',headers:{'X-CSRF-TOKEN':@json(csrf_token()),'Content-Type':'application/json','Accept':'application/json'},body:JSON.stringify({...this.note,token:this.token})});let d=await r.json();if(!r.ok){this.note.items.forEach(item=>item.validation_error='');Object.entries(d.errors||{}).forEach(([field,messages])=>{let match=field.match(/^items\\.(\\d+)\\./);if(match&&this.note.items[match[1]])this.note.items[match[1]].validation_error=messages[0]});throw new Error(d.message||Object.values(d.errors||{})[0]?.[0])}window.location.reload()}catch(e){this.error=e.message||'Não foi possível importar a nota.'}finally{this.busy=false}}}}
</script>
