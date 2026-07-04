<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Detalhe de procedimento/servico dentro de uma ordem.
 *
 * total_cost compoe a ordem, mas nao e fonte de total consolidado quando a
 * ordem ja apresenta MaintenanceRecord::total_cost.
 */
class MaintenanceRecordItem extends Model
{
    protected $fillable = [
        'maintenance_record_id',
        'procedure_id',
        'maintenance_type',
        'performed_km',
        'performed_hours',
        'performed_at',
        'total_cost',
        'extra_cost',
        'provider_name',
        'notes',
        'next_due_km',
        'next_due_hours',
        'next_due_date',
    ];

    protected $casts = [
        'performed_at' => 'date',
        'total_cost' => 'decimal:2',
        'extra_cost' => 'decimal:2',
        'next_due_date' => 'date',
    ];

    public function maintenanceRecord()
    {
        return $this->belongsTo(MaintenanceRecord::class);
    }

    public function procedure()
    {
        return $this->belongsTo(Procedure::class);
    }

    public function values()
    {
        return $this->hasMany(MaintenanceRecordItemValue::class);
    }
}
