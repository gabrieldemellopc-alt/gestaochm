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
        'provider_document',
        'fiscal_document_number',
        'fiscal_document_issued_at',
        'notes',
        'next_due_km',
        'next_due_hours',
        'next_due_date',
        'cancelled_at',
        'cancelled_by',
        'cancel_reason',
        'cancellation_type',
        'replaced_by_item_id',
        'replacement_of_item_id',
    ];

    protected $casts = [
        'performed_at' => 'date',
        'total_cost' => 'decimal:2',
        'extra_cost' => 'decimal:2',
        'next_due_date' => 'date',
        'cancelled_at' => 'datetime',
        'fiscal_document_issued_at' => 'date',
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

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class, 'maintenance_record_item_id');
    }

    public function materialUsages()
    {
        return $this->hasMany(MaintenanceMaterialUsage::class);
    }

    public function canceller()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function replacement()
    {
        return $this->belongsTo(self::class, 'replaced_by_item_id');
    }

    public function originalItem()
    {
        return $this->belongsTo(self::class, 'replacement_of_item_id');
    }

    public function scopeActive($query)
    {
        return $query->whereNull('cancelled_at');
    }

    public function scopeCancelled($query)
    {
        return $query->whereNotNull('cancelled_at');
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    public function isReplacement(): bool
    {
        return $this->replacement_of_item_id !== null;
    }
}
