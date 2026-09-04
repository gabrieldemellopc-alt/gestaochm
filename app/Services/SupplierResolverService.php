<?php

namespace App\Services;

use App\Models\Supplier;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

class SupplierResolverService
{
    public function __construct(private SupplierNormalizer $normalizer) {}

    public function resolve(int $tenantId, ?int $supplierId, ?string $name, ?string $document, ?string $resolutionAction = null, ?int $candidateSupplierId = null): ?Supplier
    {
        $resolutionAction ??= request()?->input('supplier_resolution_action');
        $candidateSupplierId ??= request()?->integer('supplier_candidate_id') ?: null;
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
        $normalizedName = $this->normalizer->normalizeName($name);
        $documentOwner = $document ? Supplier::forTenant($tenantId)->where('document', $document)->first() : null;

        if ($resolutionAction === 'enrich_existing') {
            if (! $document) {
                throw ValidationException::withMessages(['supplier_resolution_action' => 'Informe um CPF ou CNPJ válido para completar o fornecedor.']);
            }

            if ($documentOwner && (int) $documentOwner->id !== (int) $candidateSupplierId) {
                throw ValidationException::withMessages(['supplier_resolution_action' => 'Este CPF/CNPJ já está cadastrado para '.$documentOwner->displayName().'.']);
            }

            $candidate = Supplier::forTenant($tenantId)->whereKey($candidateSupplierId)->lockForUpdate()->first();
            if (! $candidate || ! $candidate->active || $candidate->normalized_name !== $normalizedName || $candidate->document) {
                throw ValidationException::withMessages(['supplier_resolution_action' => 'O fornecedor escolhido não pode receber este CPF/CNPJ.']);
            }

            $documentOwner = Supplier::forTenant($tenantId)->where('document', $document)->lockForUpdate()->first();
            if ($documentOwner && (int) $documentOwner->id !== (int) $candidate->id) {
                throw ValidationException::withMessages(['supplier_resolution_action' => 'Este CPF/CNPJ já está cadastrado para '.$documentOwner->displayName().'.']);
            }

            try {
                $candidate->update(['document' => $document, 'document_type' => $this->normalizer->detectDocumentType($document)]);
            } catch (QueryException $exception) {
                throw ValidationException::withMessages(['supplier_resolution_action' => 'Este CPF/CNPJ já está cadastrado para outro fornecedor.']);
            }
            return $candidate->fresh();
        }

        if ($documentOwner) return $documentOwner;

        $nameWithoutDocument = $document
            ? Supplier::forTenant($tenantId)->where('normalized_name', $normalizedName)->whereNull('document')->first()
            : null;

        if ($nameWithoutDocument && $resolutionAction !== 'create_new') {
            throw ValidationException::withMessages([
                'supplier_resolution_action' => 'Já existe um fornecedor com este nome, mas sem CPF/CNPJ. Escolha como deseja continuar.',
                'supplier_candidate_id' => (string) $nameWithoutDocument->id,
            ]);
        }

        if (! $document && ($supplier = Supplier::forTenant($tenantId)->where('normalized_name', $normalizedName)->first())) return $supplier;
        try { return Supplier::create(['tenant_id'=>$tenantId,'trade_name'=>$name,'document'=>$document,'document_type'=>$this->normalizer->detectDocumentType($document),'normalized_name'=>$normalizedName,'active'=>true]); }
        catch (QueryException $exception) { if ($document && ($supplier = Supplier::forTenant($tenantId)->where('document', $document)->first())) return $supplier; throw $exception; }
    }
}
