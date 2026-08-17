<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class TenantFiscalSetting extends Model { protected $fillable=['tenant_id','division_id','fiscal_document_requirements']; protected function casts(): array{return ['fiscal_document_requirements'=>'array'];} public function tenant(){return $this->belongsTo(Tenant::class);} public function division(){return $this->belongsTo(Division::class);} }