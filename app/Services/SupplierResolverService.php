<?php

namespace App\Services;

use App\Models\Supplier;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

class SupplierResolverService
{
    public function __construct(private SupplierNormalizer $normalizer) {}

    public function resolve(int $tenantId, ?int $supplierId, ?string $name, ?string $document): ?Supplier
    {
        if ($supplierId) {
            $supplier = Supplier::forTenant($tenantId)->find($supplierId);
            if (! $supplier) throw ValidationException::withMessages(['supplier_id' => 'O fornecedor selecionado não pertence a este tenant.']);
            if (! $supplier->active) throw ValidationException::withMessages(['supplier_id' => 'Fornecedor inativo não pode ser usado em novos lançamentos.']);
            return $supplier;
        }
        $name = trim((string) $name);
        if ($name === '') return null;
        $document = $this->normalizer->normalizeDocument($document);
        if ($document && ! $this->normalizer->validDocument($document)) throw ValidationException::withMessages(['supplier_document' => 'Informe um CPF ou CNPJ válido.']);
        if ($document && ($supplier = Supplier::forTenant($tenantId)->where('document', $document)->first())) return $supplier;
        $normalizedName = $this->normalizer->normalizeName($name);
        if (! $document && ($supplier = Supplier::forTenant($tenantId)->where('normalized_name', $normalizedName)->first())) return $supplier;
        try { return Supplier::create(['tenant_id'=>$tenantId,'trade_name'=>$name,'document'=>$document,'document_type'=>$this->normalizer->detectDocumentType($document),'normalized_name'=>$normalizedName,'active'=>true]); }
        catch (QueryException $exception) { if ($document && ($supplier = Supplier::forTenant($tenantId)->where('document', $document)->first())) return $supplier; throw $exception; }
    }
}
