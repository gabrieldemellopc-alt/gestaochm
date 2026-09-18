<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FuelDailyCheck extends Model
{
    protected $fillable = [
        'tenant_id',
        'division_id',
        'location_id',
        'operation_date',
        'status',
        'system_snapshot',
        'system_signature',
        'system_total_liters',
        'manual_total_liters',
        'difference_liters',
        'checked_by',
        'checked_at',
        'notes',
    ];

    protected $casts = [
        'operation_date' => 'date',
        'system_snapshot' => 'array',
        'system_total_liters' => 'decimal:3',
        'manual_total_liters' => 'decimal:3',
        'difference_liters' => 'decimal:3',
        'checked_at' => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany(FuelDailyCheckItem::class);
    }

    public function files()
    {
        return $this->hasMany(FuelDailyCheckFile::class);
    }

    public function checker()
    {
        return $this->belongsTo(User::class, 'checked_by');
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }
}
