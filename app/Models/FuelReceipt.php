<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FuelReceipt extends Model
{
    protected $fillable = [
        'tenant_id',
        'division_id',
        'location_id',
        'fuel_tank_id',
        'fuel_product_id',
        'received_at',
        'quantity_liters',
        'unit_cost',
        'total_cost',
        'supplier_name',
        'invoice_number',
        'responsible_user_id',
        'notes',
        'cancelled_at',
        'cancelled_by',
        'cancel_reason',
    ];

    protected $casts = [
        'received_at' => 'datetime',
        'quantity_liters' => 'decimal:3',
        'unit_cost' => 'decimal:4',
        'total_cost' => 'decimal:2',
        'cancelled_at' => 'datetime',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function division()
    {
        return $this->belongsTo(Division::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function tank()
    {
        return $this->belongsTo(FuelTank::class, 'fuel_tank_id');
    }

    public function product()
    {
        return $this->belongsTo(FuelProduct::class, 'fuel_product_id');
    }

    public function responsible()
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function canceller()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }
}
