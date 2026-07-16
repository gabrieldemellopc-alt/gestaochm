<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FuelFilling extends Model
{
    protected $fillable = [
        'tenant_id',
        'division_id',
        'location_id',
        'fuel_tank_id',
        'fuel_product_id',
        'vehicle_id',
        'driver_id',
        'filled_at',
        'vehicle_km',
        'vehicle_hours',
        'quantity_liters',
        'unit_cost',
        'total_cost',
        'responsible_user_id',
        'notes',
        'cancelled_at',
        'cancelled_by',
        'cancel_reason',
    ];

    protected $casts = [
        'filled_at' => 'datetime',
        'vehicle_km' => 'decimal:2',
        'vehicle_hours' => 'decimal:2',
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

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function responsible()
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function responsibleUser()
    {
        return $this->responsible();
    }

    public function canceller()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }
}
