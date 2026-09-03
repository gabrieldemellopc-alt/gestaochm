<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkshopExpense extends Model
{
    public const CATEGORIES = ['tools', 'equipment', 'ppe', 'cleaning', 'service', 'consumables', 'other'];
    public const LABELS = ['tools'=>'Ferramentas','equipment'=>'Equipamentos','ppe'=>'EPI','cleaning'=>'Limpeza','service'=>'Serviços da oficina','consumables'=>'Materiais de consumo','other'=>'Outros'];
    protected $fillable = ['tenant_id','division_id','location_id','expense_date','category','description','supplier_name','supplier_id','invoice_number','amount','notes','created_by'];
    public function supplier(){return $this->belongsTo(Supplier::class);}
    protected $casts = ['expense_date'=>'date','amount'=>'decimal:2'];
    public function location() { return $this->belongsTo(Location::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function categoryLabel(): string { return self::LABELS[$this->category] ?? $this->category; }
}
