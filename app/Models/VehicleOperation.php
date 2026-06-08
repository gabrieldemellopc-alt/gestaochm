<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VehicleOperation extends Model
{
    protected $fillable = [
        'tenant_id',
        'vehicle_id',
        'driver_id',
        'status',

        'start_vehicle_km',
        'start_vehicle_hours',
        'start_datetime_reported',
        'start_datetime_system',
        'start_clock_difference_minutes',
        'start_observation',
        'start_delay_reason',
        'start_delay_justification',

        'end_vehicle_km',
        'end_vehicle_hours',
        'end_datetime_reported',
        'end_datetime_system',
        'end_clock_difference_minutes',
        'end_observation',
        'end_delay_reason',
        'end_delay_justification',

        'created_by',
        'closed_by',
    ];

    protected $casts = [
        'start_datetime_reported' => 'datetime',
        'start_datetime_system' => 'datetime',
        'end_datetime_reported' => 'datetime',
        'end_datetime_system' => 'datetime',

        'start_vehicle_km' => 'decimal:2',
        'start_vehicle_hours' => 'decimal:2',
        'end_vehicle_km' => 'decimal:2',
        'end_vehicle_hours' => 'decimal:2',
    ];

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function closer()
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function getIsOpenAttribute(): bool
    {
        return $this->status === 'open';
    }
}