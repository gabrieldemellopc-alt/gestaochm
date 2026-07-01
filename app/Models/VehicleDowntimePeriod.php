<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VehicleDowntimePeriod extends Model
{
    protected $fillable = [
        'vehicle_id',
        'status',
        'reason',
        'started_at',
        'ended_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function getIsOpenAttribute(): bool
    {
        return is_null($this->ended_at);
    }
}