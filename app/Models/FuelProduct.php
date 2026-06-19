<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FuelProduct extends Model
{
    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'unit',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function tanks()
    {
        return $this->hasMany(FuelTank::class);
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
