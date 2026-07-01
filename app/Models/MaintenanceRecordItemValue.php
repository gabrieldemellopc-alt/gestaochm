<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaintenanceRecordItemValue extends Model
{
    protected $fillable = [
        'maintenance_record_item_id',
        'procedure_field_id',
        'value',
        'quantity',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
    ];

    public function item()
    {
        return $this->belongsTo(MaintenanceRecordItem::class, 'maintenance_record_item_id');
    }

    public function field()
    {
        return $this->belongsTo(ProcedureField::class, 'procedure_field_id');
    }
}