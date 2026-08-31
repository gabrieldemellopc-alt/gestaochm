<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class MaintenanceMaterialUsage extends Model
{
    protected $fillable = [
        'tenant_id', 'location_id', 'maintenance_record_id', 'maintenance_record_item_id', 'stock_item_id',
        'stock_movement_id', 'purchase_entry_movement_id', 'quantity', 'unit_cost', 'total_cost', 'notes',
        'used_at', 'created_by', 'cancelled_at', 'cancelled_by', 'cancel_reason',
        'reversal_movement_id', 'replaced_by_usage_id', 'replaces_usage_id',
    ];

    protected $casts = [
        'quantity' => 'decimal:2', 'unit_cost' => 'decimal:2',
        'total_cost' => 'decimal:2', 'used_at' => 'datetime', 'cancelled_at' => 'datetime',
    ];

    public function scopeActive(Builder $query): Builder { return $query->whereNull('cancelled_at'); }
    public function scopeCancelled(Builder $query): Builder { return $query->whereNotNull('cancelled_at'); }
    public function maintenanceRecord() { return $this->belongsTo(MaintenanceRecord::class); }
    public function maintenanceRecordItem() { return $this->belongsTo(MaintenanceRecordItem::class); }
    public function stockItem() { return $this->belongsTo(StockItem::class); }
    public function stockMovement() { return $this->belongsTo(StockMovement::class); }
    public function purchaseEntryMovement() { return $this->belongsTo(StockMovement::class, 'purchase_entry_movement_id'); }
    public function reversalMovement() { return $this->belongsTo(StockMovement::class, 'reversal_movement_id'); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function canceller() { return $this->belongsTo(User::class, 'cancelled_by'); }
    public function replacement() { return $this->belongsTo(self::class, 'replaced_by_usage_id'); }
    public function replacedUsage() { return $this->belongsTo(self::class, 'replaces_usage_id'); }
    public function original() { return $this->replacedUsage(); }
}
