@props([
    'name' => 'supplier_name',
    'documentName' => 'supplier_document',
    'idName' => 'supplier_id',
    'value' => '',
    'documentValue' => '',
    'idValue' => '',
    'textName' => null,
    'supplierIdValue' => null,
    'initialText' => null,
    'initialDocument' => null,
    'textModel' => null,
    'idModel' => null,
    'documentModel' => null,
    'showLabel' => false,
    'required' => false,
    'label' => 'Fornecedor',
    'placeholder' => 'Digite para buscar ou cadastrar',
])

<div
    x-data="supplierAutocomplete()"
    x-init="init()"
    {{ $attributes->class(['chm-supplier-picker']) }}
>
    @if($showLabel)<label class="chm-supplier-picker__label">{{ $label }}@if($required) <span>*</span>@endif</label>@endif
    <div class="chm-supplier-picker__fields {{ $documentName ? 'chm-supplier-picker__fields--split' : '' }}">
        <div class="chm-supplier-picker__field">
            <input
                x-ref="name"
                name="{{ $textName ?? $name }}"
                value="{{ old($textName ?? $name, $initialText ?? $value) }}"
                @if($textModel) x-model="{{ $textModel }}" @endif
                @input="changed()"
                @focus="open = true"
                autocomplete="off"
                placeholder="{{ $placeholder }}"
                @if($required) required @endif
            >
            <input x-ref="id" type="hidden" name="{{ $idName }}" value="{{ old($idName, $supplierIdValue ?? $idValue) }}" @if($idModel) x-model="{{ $idModel }}" @endif>
            <input x-ref="resolutionAction" type="hidden" name="supplier_resolution_action" value="{{ old('supplier_resolution_action') }}">
            <input x-ref="candidateId" type="hidden" name="supplier_candidate_id" value="{{ old('supplier_candidate_id') }}">
        </div>

        @if($documentName)
            <div class="chm-supplier-picker__field">
                <input
                    x-ref="document"
                    name="{{ $documentName }}"
                    value="{{ old($documentName, $initialDocument ?? $documentValue) }}"
                    @if($documentModel) x-model="{{ $documentModel }}" @endif
                    @input="documentChanged()"
                    @blur="validateDocument(true)"
                    autocomplete="off"
                    inputmode="numeric"
                    placeholder="CPF ou CNPJ"
                    :class="{ 'is-invalid': documentError }"
                >
            </div>
        @endif
    </div>

    <div x-show="state !== 'idle' || documentError" x-cloak class="chm-supplier-picker__status">
        <p x-show="selected && state === 'selected'" class="chm-supplier-picker__selected">
            Fornecedor cadastrado: <strong x-text="selected?.name"></strong><span x-show="!selected?.document"> — Sem CPF/CNPJ</span>
            <button type="button" @click="clearSelection()">Trocar fornecedor</button>
        </p>

        <div x-show="state === 'suggestion' && open && results.length" class="chm-supplier-picker__results">
            <template x-for="item in results" :key="item.id">
                <button type="button" @click="select(item)">
                    <strong x-text="item.name"></strong>
                    <span x-text="item.document || 'Sem CPF/CNPJ'"></span>
                </button>
            </template>
        </div>

        <div x-show="state === 'ambiguous' || state === 'resolution_chosen'" class="chm-supplier-picker__ambiguity">
            <strong>Este fornecedor está cadastrado sem CPF/CNPJ.</strong>
            <span x-text="ambiguity?.name"></span>
            <small>CPF/CNPJ informado: <span x-text="displayDocument(digits())"></span></small>
            <small>Escolha o que deseja fazer.</small>
            <div class="chm-supplier-picker__resolution" role="group" aria-label="Decisão do fornecedor">
                <button type="button" @click="enrichExisting()" :class="{ 'is-selected': resolutionAction === 'enrich_existing' }"><span x-show="resolutionAction === 'enrich_existing'">✓ </span>Atualizar cadastro existente</button>
                <button type="button" @click="createNew()" :class="{ 'is-selected': resolutionAction === 'create_new' }"><span x-show="resolutionAction === 'create_new'">✓ </span>Cadastrar como novo</button>
            </div>
            <small x-show="state === 'resolution_chosen'" x-text="resolutionAction === 'enrich_existing' ? 'Este CPF/CNPJ será incluído no fornecedor existente ao salvar o lançamento.' : 'Um novo fornecedor será criado ao salvar o lançamento.'"></small>
            <button type="button" class="chm-supplier-picker__change" @click="clearSelection()">Trocar fornecedor</button>
        </div>

        <div x-show="state === 'document_owner' || state === 'same_name_document'" class="chm-supplier-picker__ambiguity">
            <strong x-text="documentOwner ? 'Este CPF/CNPJ já está cadastrado para:' : 'Já existe fornecedor com este nome e outro CPF/CNPJ.'"></strong>
            <span x-text="(documentOwner || sameNameWithDocument)?.name"></span>
            <small x-text="documentOwner ? 'O documento identifica o fornecedor já cadastrado.' : 'Use o cadastro existente ou confirme a criação de outro fornecedor com o documento informado.'"></small>
            <div>
                <button type="button" @click="useExisting(documentOwner || sameNameWithDocument)">Usar fornecedor cadastrado</button>
                <button x-show="!documentOwner" type="button" @click="createNew(sameNameWithDocument)">Cadastrar como novo</button>
            </div>
        </div>

        <small x-show="documentError" x-text="documentError" class="chm-supplier-picker__error"></small>
        <p x-show="state === 'new'" class="chm-supplier-picker__manual">Nenhum fornecedor selecionado. O nome informado será usado como fornecedor manual.</p>
    </div>
</div>

@once
    <script>
        function supplierAutocomplete() {
            return {
                open: false, loading: false, results: [], selected: null, ambiguity: null, documentOwner: null, sameNameWithDocument: null,
                state: 'idle', resolutionAction: '', controller: null, requestId: 0, searchTimer: null, documentError: '',
                init() {
                    const id = this.$refs.id?.value;
                    const name = this.$refs.name?.value?.trim();
                    if (id && name) { this.selected = { id, name, document: this.digits() || null }; this.state = 'selected'; }
                    this.$el.closest('form')?.addEventListener('submit', event => {
                        if (this.state === 'ambiguous' && !this.$refs.resolutionAction.value) {
                            event.preventDefault();
                            return;
                        }
                        if (!this.validateDocument(true)) {
                            event.preventDefault();
                            this.$refs.document?.focus();
                        }
                    });
                },
                digits() { return (this.$refs.document?.value || '').replace(/\D/g, ''); },
                isCompleteValidDocument(value = this.digits()) { return value.length === 11 ? this.validCpf(value) : value.length === 14 ? this.validCnpj(value) : false; },
                validCpf(value) {
                    if (!/^\d{11}$/.test(value) || /^(\d)\1{10}$/.test(value)) return false;
                    const digit = length => { let sum = 0; for (let i = 0; i < length; i++) sum += Number(value[i]) * (length + 1 - i); const rest = (sum * 10) % 11; return rest === 10 ? 0 : rest; };
                    return digit(9) === Number(value[9]) && digit(10) === Number(value[10]);
                },
                validCnpj(value) {
                    if (!/^\d{14}$/.test(value) || /^(\d)\1{13}$/.test(value)) return false;
                    const digit = length => { const weights = length === 12 ? [5,4,3,2,9,8,7,6,5,4,3,2] : [6,5,4,3,2,9,8,7,6,5,4,3,2]; const sum = weights.reduce((total, weight, index) => total + Number(value[index]) * weight, 0); const rest = sum % 11; return rest < 2 ? 0 : 11 - rest; };
                    return digit(12) === Number(value[12]) && digit(13) === Number(value[13]);
                },
                formatDocument() {
                    const value = this.digits().slice(0, 14);
                    if (!this.$refs.document) return;
                    this.$refs.document.value = value.length <= 11
                        ? value.replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d{1,2})$/, '$1-$2')
                        : value.replace(/^(\d{2})(\d)/, '$1.$2').replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3').replace(/\.(\d{3})(\d)/, '.$1/$2').replace(/(\d{4})(\d)/, '$1-$2');
                },
                validateDocument(strict = false) {
                    if (!this.$refs.document) return true;
                    const value = this.digits();
                    if (!value) { this.documentError = ''; return true; }
                    const valid = this.isCompleteValidDocument(value);
                    this.documentError = (!valid && strict) ? 'Informe um CPF ou CNPJ válido.' : '';
                    return valid;
                },
                clearResolution() { this.ambiguity = null; this.documentOwner = null; this.sameNameWithDocument = null; this.resolutionAction = ''; this.$refs.resolutionAction.value = ''; this.$refs.candidateId.value = ''; },
                cancelScheduledSearch() { clearTimeout(this.searchTimer); this.searchTimer = null; },
                scheduleSearch(query, delay = 300) { this.cancelScheduledSearch(); this.searchTimer = setTimeout(() => this.search(query), delay); },
                changed() { this.$refs.id.value = ''; this.selected = null; this.clearResolution(); const query = this.$refs.name.value.trim(); if (query.length >= 2) { this.state = 'searching'; this.scheduleSearch(query); } else { this.cancelScheduledSearch(); this.results = []; this.open = false; this.state = 'idle'; } },
                documentChanged() {
                    this.formatDocument(); this.validateDocument(false); this.cancelScheduledSearch();
                    const value = this.digits(); const name = this.$refs.name.value.trim();
                    if (this.selected) {
                        const selectedDocument = this.digitsFrom(this.selected.document || this.selected.formatted_document);
                        if (!value) { this.clearResolution(); this.$refs.id.value = this.selected.id; this.state = 'selected'; return; }
                        if (value === selectedDocument) { this.clearResolution(); this.$refs.id.value = this.selected.id; this.state = 'selected'; return; }
                        this.$refs.id.value = '';
                        this.clearResolution();
                        if (!this.isCompleteValidDocument(value)) { this.state = 'selected'; return; }
                        this.state = 'searching'; this.scheduleSearch(value, 300); return;
                    }
                    if (!this.isCompleteValidDocument(value)) return;
                    this.clearResolution(); this.state = 'searching'; this.scheduleSearch(name || value, 300);
                },
                digitsFrom(value) { return String(value || '').replace(/\D/g, ''); },
                async search(query) {
                    this.controller?.abort(); this.controller = new AbortController(); const id = ++this.requestId; this.loading = true;
                    try {
                        const typedDocument = this.digits();
                        const document = this.isCompleteValidDocument(typedDocument) ? typedDocument : '';
                        const response = await fetch(`/suppliers/search?q=${encodeURIComponent(query)}&document=${encodeURIComponent(document)}`, { headers: { Accept: 'application/json' }, signal: this.controller.signal });
                        if (!response.ok) throw new Error('search');
                        const items = await response.json(); if (id !== this.requestId) return;
                        this.results = items; this.open = !this.selected;
                        const owner = document && items.find(item => item.document === document);
                        this.documentOwner = owner || null;
                        this.ambiguity = !owner && document && (this.selected?.document ? null : (this.selected || items.find(item => item.exact_name && !item.document))) || null;
                        this.sameNameWithDocument = !owner && !this.ambiguity && document && (this.selected?.document ? this.selected : items.find(item => item.exact_name && item.document)) || null;
                        this.state = owner ? 'document_owner' : this.ambiguity ? 'ambiguous' : this.sameNameWithDocument ? 'same_name_document' : this.selected ? 'selected' : items.length ? 'suggestion' : this.nameOrNew(query);
                    } catch (error) { if (error.name !== 'AbortError') { this.results = []; this.open = false; this.state = this.selected ? 'selected' : 'idle'; } } finally { if (id === this.requestId) this.loading = false; }
                },
                select(item) {
                    this.selected = item; this.$refs.id.value = item.id; this.$refs.name.value = item.name;
                    if (this.$refs.document && item.document) this.$refs.document.value = this.displayDocument(item.document);
                    this.clearResolution(); this.open = false; this.results = []; this.state = 'selected';
                },
                enrichExisting() { this.$refs.id.value = ''; this.resolutionAction = 'enrich_existing'; this.$refs.resolutionAction.value = this.resolutionAction; this.$refs.candidateId.value = this.ambiguity.id; this.state = 'resolution_chosen'; },
                createNew(candidate = this.ambiguity) { this.$refs.id.value = ''; this.resolutionAction = 'create_new'; this.$refs.resolutionAction.value = this.resolutionAction; this.$refs.candidateId.value = candidate?.id || ''; this.state = 'resolution_chosen'; },
                useExisting(item) { this.select(item); this.resolutionAction = 'use_existing'; this.$refs.resolutionAction.value = this.resolutionAction; },
                clearSelection() { this.cancelScheduledSearch(); this.selected = null; this.$refs.id.value = ''; this.clearResolution(); this.results = []; this.open = false; this.state = 'idle'; this.$refs.name?.focus(); },
                displayDocument(value) { const digits = String(value).replace(/\D/g, ''); return digits.length === 11 ? `${digits.slice(0,3)}.${digits.slice(3,6)}.${digits.slice(6,9)}-${digits.slice(9)}` : `${digits.slice(0,2)}.${digits.slice(2,5)}.${digits.slice(5,8)}/${digits.slice(8,12)}-${digits.slice(12)}`; },
                nameOrNew(query) { return String(query || '').trim().length >= 2 ? 'new' : 'idle'; },
            };
        }
    </script>
@endonce
