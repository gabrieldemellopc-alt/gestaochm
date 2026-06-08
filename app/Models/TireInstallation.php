<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TireInstallation extends Model
{
    protected $fillable = [
        'tenant_id',
        'tire_id',
        'vehicle_id',
        'position_code',
        'installed_at',
        'installed_km',
        'removed_at',
        'removed_km',
        'removal_reason',
        'active',
        'created_by',
    ];

    protected $casts = [
        'installed_at' => 'date',
        'removed_at' => 'date',
        'active' => 'boolean',
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

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function measurements()
    {
        return $this->hasMany(TireMeasurement::class, 'tire_id', 'tire_id')
            ->whereColumn('vehicle_id', 'vehicle_id')
            ->whereColumn('position_code', 'position_code');
    }
}