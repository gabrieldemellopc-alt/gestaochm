<?php
namespace App\Services;
use App\Models\Supplier;
class SupplierSnapshotService{public function fromResolvedSupplier(?Supplier $supplier, ?string $manualName = null):array{return $supplier?['supplier_id'=>$supplier->id,'supplier_name'=>$supplier->displayName(),'supplier_document'=>$supplier->document]:['supplier_id'=>null,'supplier_name'=>$manualName,'supplier_document'=>null];}public function maintenanceProvider(?Supplier $supplier, ?string $manualName = null):array{$snapshot=$this->fromResolvedSupplier($supplier,$manualName);return ['supplier_id'=>$snapshot['supplier_id'],'supplier_name'=>$snapshot['supplier_name'],'provider_document'=>$snapshot['supplier_document']];}}
