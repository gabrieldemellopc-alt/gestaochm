<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Movimento de estoque.
 *
 * Em relatorios de estoque, total_cost e a fonte oficial do custo do
 * movimento. Quando vinculado a manutencao, e detalhe de pecas consumidas e
 * nao deve ser somado novamente ao MaintenanceRecord::total_cost.
 */
class StockMovement extends Model
{
    protected $fillable = [
        'tenant_id',
        'location_id',
        'stock_item_id',
        'maintenance_record_id',
        'movement_type',
        'quantity',
        'unit_cost',
        'description',
        'cancelled_at',
        'cancelled_by',
        'cancel_reason',
        'reversal_movement_id',
        'reversed_from_movement_id',
        'total_cost',
        'invoice_number',
        'supplier_name',
        'moved_at',
        'maintenance_record_item_id',
    ];

    protected $casts = [
        'cancelled_at' => 'datetime',
        'total_cost' => 'decimal:2',
        'moved_at' => 'datetime',
    ];

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function stockItem()
    {
        return $this->belongsTo(StockItem::class);
    }

    public function maintenanceRecord()
    {
        return $this->belongsTo(MaintenanceRecord::class);
    }

    public function canceller()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function reversalMovement()
    {
        return $this->belongsTo(self::class, 'reversal_movement_id');
    }

    public function reversedFromMovement()
    {
        return $this->belongsTo(self::class, 'reversed_from_movement_id');
    }
    public function maintenanceRecordItem()
    {
        return $this->belongsTo(MaintenanceRecordItem::class);
    }
    
}
