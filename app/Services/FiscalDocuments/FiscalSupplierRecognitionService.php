<?php

namespace App\Services\FiscalDocuments;

use App\Models\Supplier;
use App\Services\SupplierNormalizer;
use App\Services\SupplierSearchService;
use Illuminate\Validation\ValidationException;

class FiscalSupplierRecognitionService
{
    public function __construct(private SupplierNormalizer $normalizer, private SupplierSearchService $search) {}

    public function recognize(int $tenantId, ?string $name, ?string $document, string $source): array
    {
        $document = $this->normalizer->normalizeDocument((string) $document);
        $validCnpj = strlen($document) === 14 && $this->normalizer->validateCnpj($document);
        if ($validCnpj) {
            $supplier = Supplier::forTenant($tenantId)->where('document', $document)->first();
            return ['valid_cnpj'=>true, 'supplier_id'=>$supplier?->id, 'supplier_name'=>$supplier?->displayName(), 'supplier_document'=>$supplier?->formattedDocument(), 'suggestions'=>[]];
        }
        return ['valid_cnpj'=>false, 'supplier_id'=>null, 'supplier_name'=>null, 'supplier_document'=>null,
            'suggestions'=>$source === 'pdf' && trim((string) $name) !== '' ? $this->search->search($tenantId, $name) : []];
    }

    public function resolve(int $tenantId, ?int $supplierId): ?Supplier
    {
        if (! $supplierId) return null;
        $supplier = Supplier::forTenant($tenantId)->whereKey($supplierId)->first();
        if (! $supplier) throw ValidationException::withMessages(['supplier_id'=>'O fornecedor selecionado não pertence a este tenant.']);
        if (! $supplier->active) throw ValidationException::withMessages(['supplier_id'=>'Fornecedor inativo não pode ser vinculado a uma nova nota fiscal.']);
        return $supplier;
    }

    public function createFromFiscal(int $tenantId, string $name, ?string $document): Supplier
    {
        $document = $this->normalizer->normalizeDocument((string) $document);
        $validDocument = (strlen($document) === 14 && $this->normalizer->validateCnpj($document)) || (strlen($document) === 11 && $this->normalizer->validateCpf($document));
        if (strlen($document) === 14 && $this->normalizer->validateCnpj($document)) {
            $existing = Supplier::forTenant($tenantId)->where('document', $document)->first();
            if ($existing) return $existing;
        }
        return Supplier::create(['tenant_id'=>$tenantId, 'trade_name'=>$name, 'document'=>$validDocument ? $document : null,
            'document_type'=>$validDocument ? (strlen($document) === 14 ? 'cnpj' : 'cpf') : null,
            'normalized_name'=>$this->normalizer->normalizeName($name), 'active'=>true]);
    }
}
