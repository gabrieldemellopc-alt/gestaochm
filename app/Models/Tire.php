<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tire extends Model
{
    protected $fillable = [
        'tenant_id',
        'location_id',
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

    protected $casts = [
        'purchase_date' => 'date',
        'initial_tread_depth' => 'decimal:2',
        'warning_tread_depth' => 'decimal:2',
        'critical_tread_depth' => 'decimal:2',
    ];

    public function entry()
    {
        return $this->belongsTo(TireEntry::class, 'entry_id');
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
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
        return $this->hasMany(TireMeasurement::class)
            ->whereNull('cancelled_at');
    }

    public function allMeasurements()
    {
        return $this->hasMany(TireMeasurement::class);
    }

    public function latestMeasurement()
    {
        return $this->hasOne(TireMeasurement::class)
            ->whereNull('cancelled_at')
            ->ofMany([
                'measured_at' => 'max',
                'id' => 'max',
            ]);
    }

    public function retreads()
    {
        return $this->hasMany(TireRetread::class)
            ->whereNull('cancelled_at');
    }

    public function allRetreads()
    {
        return $this->hasMany(TireRetread::class);
    }

    public function latestRetread()
    {
        return $this->hasOne(TireRetread::class)
            ->whereNull('cancelled_at')
            ->ofMany([
                'retreaded_at' => 'max',
                'id' => 'max',
            ]);
    }

    public function scopeWithCurrentTreadContext($query)
    {
        return $query->with([
            'latestMeasurement',
            'latestRetread',
        ]);
    }

    public function getCurrentTreadDepthAttribute()
    {
        return $this->currentTreadContext()['depth'];
    }

    public function getCurrentTreadSourceAttribute(): string
    {
        return $this->currentTreadContext()['source'];
    }

    public function getCurrentTreadDateAttribute()
    {
        return $this->currentTreadContext()['date'];
    }

    public function getTreadReferenceDepthAttribute()
    {
        return $this->latestRetread?->new_tread_depth
            ?? $this->initial_tread_depth;
    }

    private function currentTreadContext(): array
    {
        $measurement = $this->latestMeasurement;
        $retread = $this->latestRetread;

        if (
            $measurement
            && (
                ! $retread
                || $measurement->measured_at->greaterThan($retread->retreaded_at)
            )
        ) {
            return [
                'depth' => $measurement->minimum_tread,
                'source' => 'measurement',
                'date' => $measurement->measured_at,
            ];
        }

        if ($retread) {
            return [
                'depth' => $retread->new_tread_depth,
                'source' => 'retread',
                'date' => $retread->retreaded_at,
            ];
        }

        return [
            'depth' => $this->initial_tread_depth,
            'source' => 'initial',
            'date' => $this->purchase_date,
        ];
    }
}
