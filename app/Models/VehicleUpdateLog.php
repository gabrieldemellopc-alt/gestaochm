<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VehicleUpdateLog extends Model
{
    public const READING_STATUS_VALID = 'valid';
    public const READING_STATUS_SUSPECT = 'suspect';
    public const READING_STATUS_IGNORED = 'ignored';
    protected $fillable = [

        'vehicle_id',
        'user_id',
        'division_id',
        'location_id',
        'type',
        'source',
        'read_at',
        'fuel_filling_id',
        'reading_status', 'reading_issue', 'reviewed_by', 'reviewed_at',
        'old_value',
        'new_value',
        'observation',

    ];

    protected $casts = [
        'read_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function fuelFilling()
    {
        return $this->belongsTo(FuelFilling::class);
    }

    public function reviewer() { return $this->belongsTo(User::class, 'reviewed_by'); }
    public function scopeUsableReading($query) { return $query->where(fn ($q) => $q->whereNull('reading_status')->orWhere('reading_status', self::READING_STATUS_VALID)); }
    public function getIsReadingUsableAttribute(): bool { return in_array($this->reading_status, [null, self::READING_STATUS_VALID], true); }
}
