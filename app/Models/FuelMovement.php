<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FuelMovement extends Model
{
    public const TYPE_RECEIPT = 'receipt';
    public const TYPE_FILLING = 'filling';
    public const TYPE_ADJUSTMENT = 'adjustment';
    public const TYPE_REVERSAL = 'reversal';

    protected $fillable = [
        'tenant_id',
        'division_id',
        'location_id',
        'fuel_tank_id',
        'fuel_product_id',
        'movement_type',
        'quantity_liters',
        'balance_before',
        'balance_after',
        'source_type',
        'source_id',
        'responsible_user_id',
        'notes',
    ];

    protected $casts = [
        'quantity_liters' => 'decimal:3',
        'balance_before' => 'decimal:3',
        'balance_after' => 'decimal:3',
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

    public function responsibleUser()
    {
        return $this->responsible();
    }
}
