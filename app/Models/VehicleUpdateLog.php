<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VehicleUpdateLog extends Model
{
    protected $fillable = [

        'vehicle_id',
        'user_id',
        'division_id',
        'location_id',
        'type',
        'source',
        'old_value',
        'new_value',
        'observation',

    ];

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}