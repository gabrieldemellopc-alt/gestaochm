<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tire extends Model
{
    protected $fillable = [
        'tenant_id',
        'code',
        'brand',
        'model',
        'initial_tread_depth',
        'purchase_date',
        'status',
        'entry_id',
        'size',
        'unit_cost',
        'warning_tread_depth',
        'critical_tread_depth',
        'notes',
    ];
    public function entry()
    {
        return $this->belongsTo(TireEntry::class, 'entry_id');
    }
    protected $casts = [
        'purchase_date' => 'date',
        'initial_tread_depth' => 'decimal:2',
        'warning_tread_depth' => 'decimal:2',
        'critical_tread_depth' => 'decimal:2',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function installations()
    {
        return $this->hasMany(TireInstallation::class);
    }

    public function activeInstallation()
    {
        return $this->hasOne(TireInstallation::class)
            ->where('active', true);
    }

    public function measurements()
    {
        return $this->hasMany(TireMeasurement::class);
    }

    public function latestMeasurement()
    {
        return $this->hasOne(TireMeasurement::class)
            ->latestOfMany('measured_at');
    }
}