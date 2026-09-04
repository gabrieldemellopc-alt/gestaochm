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

    <div x-show="selected || (open && results.length) || ambiguity || documentOwner || sameNameWithDocument || manual || documentError" x-cloak class="chm-supplier-picker__status">
        <p x-show="selected" class="chm-supplier-picker__selected">
            Fornecedor selecionado: <strong x-text="selected?.name"></strong>
            <button type="button" @click="clearSelection()">Trocar</button>
        </p>

        <div x-show="open && results.length" class="chm-supplier-picker__results">
            <template x-for="item in results" :key="item.id">
                <button type="button" @click="select(item)">
                    <strong x-text="item.name"></strong>
                    <span x-text="item.document || 'Sem CPF/CNPJ'"></span>
                </button>
            </template>
        </div>

        <div x-show="ambiguity" class="chm-supplier-picker__ambiguity">
            <strong>Já existe um fornecedor com este nome, mas sem CPF/CNPJ.</strong>
            <span x-text="ambiguity?.name"></span>
            <small>Escolha se deseja completar o cadastro existente ou criar outro fornecedor.</small>
            <div>
                <button type="button" @click="enrichExisting()">Atualizar fornecedor existente</button>
                <button type="button" @click="createNew()">Cadastrar como novo</button>
            </div>
        </div>

        <div x-show="documentOwner || sameNameWithDocument" class="chm-supplier-picker__ambiguity">
            <strong x-text="documentOwner ? 'Este CPF/CNPJ já está cadastrado para:' : 'Já existe fornecedor com este nome e outro CPF/CNPJ.'"></strong>
            <span x-text="(documentOwner || sameNameWithDocument)?.name"></span>
            <small x-text="documentOwner ? 'O documento identifica o fornecedor já cadastrado.' : 'Use o cadastro existente ou confirme a criação de outro fornecedor com o documento informado.'"></small>
            <div>
                <button type="button" @click="useExisting(documentOwner || sameNameWithDocument)">Usar fornecedor cadastrado</button>
                <button x-show="!documentOwner" type="button" @click="createNew(sameNameWithDocument)">Cadastrar como novo</button>
            </div>
        </div>

        <small x-show="documentError" x-text="documentError" class="chm-supplier-picker__error"></small>
        <p x-show="manual" class="chm-supplier-picker__manual">Nenhum fornecedor selecionado. O nome informado será usado como fornecedor manual.</p>
    </div>
</div>

@once
    <script>
        function supplierAutocomplete() {
            return {
                open: false, loading: false, results: [], selected: null, ambiguity: null, documentOwner: null, sameNameWithDocument: null,
                controller: null, requestId: 0, searchTimer: null, documentError: '',
                init() {
                    const id = this.$refs.id?.value;
                    const name = this.$refs.name?.value?.trim();
                    if (id && name) this.selected = { id, name };
                    this.$el.closest('form')?.addEventListener('submit', event => {
                        if (this.ambiguity && !this.$refs.resolutionAction.value) {
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
                clearResolution() { this.ambiguity = null; this.documentOwner = null; this.sameNameWithDocument = null; this.$refs.resolutionAction.value = ''; this.$refs.candidateId.value = ''; },
                cancelScheduledSearch() { clearTimeout(this.searchTimer); this.searchTimer = null; },
                scheduleSearch(query, delay = 300) { this.cancelScheduledSearch(); this.searchTimer = setTimeout(() => this.search(query), delay); },
                changed() { this.$refs.id.value = ''; this.selected = null; this.clearResolution(); const query = this.$refs.name.value.trim(); if (query.length >= 2) this.scheduleSearch(query); else { this.cancelScheduledSearch(); this.results = []; this.open = false; } },
                documentChanged() {
                    this.formatDocument(); this.validateDocument(false); this.cancelScheduledSearch();
                    const value = this.digits(); const name = this.$refs.name.value.trim();
                    if (this.selected && value !== this.digitsFrom(this.selected.document || this.selected.formatted_document)) this.clearSelection();
                    if (!this.isCompleteValidDocument(value)) return;
                    this.clearResolution(); this.scheduleSearch(name || value, 300);
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
                        this.results = items; this.open = true;
                        const owner = document && items.find(item => item.document === document);
                        this.documentOwner = owner || null;
                        this.ambiguity = !owner && document && items.find(item => item.exact_name && !item.document) || null;
                        this.sameNameWithDocument = !owner && !this.ambiguity && document && items.find(item => item.exact_name && item.document) || null;
                    } catch (error) { if (error.name !== 'AbortError') { this.results = []; this.open = false; } } finally { if (id === this.requestId) this.loading = false; }
                },
                select(item) {
                    this.selected = item; this.$refs.id.value = item.id; this.$refs.name.value = item.name;
                    if (this.$refs.document && item.document) this.$refs.document.value = this.displayDocument(item.document);
                    this.clearResolution(); this.open = false; this.results = [];
                },
                enrichExisting() { this.$refs.resolutionAction.value = 'enrich_existing'; this.$refs.candidateId.value = this.ambiguity.id; },
                createNew(candidate = this.ambiguity) { this.$refs.resolutionAction.value = 'create_new'; this.$refs.candidateId.value = candidate?.id || ''; },
                useExisting(item) { this.select(item); this.$refs.resolutionAction.value = 'use_existing'; },
                clearSelection() { this.selected = null; this.$refs.id.value = ''; this.clearResolution(); },
                displayDocument(value) { const digits = String(value).replace(/\D/g, ''); return digits.length === 11 ? `${digits.slice(0,3)}.${digits.slice(3,6)}.${digits.slice(6,9)}-${digits.slice(9)}` : `${digits.slice(0,2)}.${digits.slice(2,5)}.${digits.slice(5,8)}/${digits.slice(8,12)}-${digits.slice(12)}`; },
                get manual() { return !this.selected && this.$refs?.name?.value?.trim() && !this.ambiguity; },
            };
        }
    </script>
@endonce
