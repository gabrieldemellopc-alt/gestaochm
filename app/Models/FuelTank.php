<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FuelTank extends Model
{
    protected $fillable = [
        'tenant_id',
        'division_id',
        'location_id',
        'fuel_product_id',
        'name',
        'capacity_liters',
        'current_balance_liters',
        'minimum_balance_liters',
        'active',
        'average_unit_cost',
        'estimated_stock_value',
    ];

    protected $casts = [
        'capacity_liters' => 'decimal:3',
        'current_balance_liters' => 'decimal:3',
        'minimum_balance_liters' => 'decimal:3',
        'active' => 'boolean',
        'average_unit_cost' => 'decimal:4',
        'estimated_stock_value' => 'decimal:2',
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

    public function product()
    {
        return $this->belongsTo(FuelProduct::class, 'fuel_product_id');
    }

    public function receipts()
    {
        return $this->hasMany(FuelReceipt::class);
    }

    public function fillings()
    {
        return $this->hasMany(FuelFilling::class);
    }

    public function movements()
    {
        return $this->hasMany(FuelMovement::class);
    }
}
