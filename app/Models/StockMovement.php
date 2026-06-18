<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
    ];

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function maintenanceRecord()
    {
        return $this->belongsTo(MaintenanceRecord::class);
    }
}
