<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TireMeasurement extends Model
{
    protected $fillable = [
        'tenant_id',
        'tire_id',
        'vehicle_id',
        'position_code',
        'measured_at',
        'vehicle_km',
        'outer_tread',
        'center_outer_tread',
        'center_inner_tread',
        'inner_tread',
        'average_tread',
        'minimum_tread',
        'notes',
        'cancelled_at',
        'cancelled_by',
        'cancel_reason',
        'user_id',
    ];

    protected $casts = [
        'measured_at' => 'date',
        'outer_tread' => 'decimal:2',
        'center_outer_tread' => 'decimal:2',
        'center_inner_tread' => 'decimal:2',
        'inner_tread' => 'decimal:2',
        'average_tread' => 'decimal:2',
        'minimum_tread' => 'decimal:2',
        'cancelled_at' => 'datetime',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function tire()
    {
        return $this->belongsTo(Tire::class);
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function canceller()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function getIsCancelledAttribute(): bool
    {
        return $this->cancelled_at !== null;
    }
}
