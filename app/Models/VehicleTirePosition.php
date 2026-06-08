<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VehicleTirePosition extends Model
{
    protected $fillable = [
        'tenant_id',
        'vehicle_id',
        'code',
        'label',
        'sort_order',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }
}