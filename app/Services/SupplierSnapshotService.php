<?php
namespace App\Services;
use App\Models\Supplier;use Illuminate\Validation\ValidationException;
class SupplierSnapshotService{public function resolve(int $tenantId,?int $supplierId,?string $manualName):array{if(!$supplierId)return ['supplier_id'=>null,'supplier_name'=>$manualName];$s=Supplier::forTenant($tenantId)->whereKey($supplierId)->first();if(!$s)throw ValidationException::withMessages(['supplier_id'=>'O fornecedor selecionado não pertence a este tenant.']);if(!$s->active)throw ValidationException::withMessages(['supplier_id'=>'Fornecedor inativo não pode ser usado em novos lançamentos.']);return ['supplier_id'=>$s->id,'supplier_name'=>$s->displayName()];}}
