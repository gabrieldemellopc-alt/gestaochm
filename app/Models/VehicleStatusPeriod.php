<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VehicleStatusPeriod extends Model
{
    protected $fillable = [
        'vehicle_id',
        'status',
        'started_at',
        'ended_at',
        'changed_by',
        'reason',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function changer()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
