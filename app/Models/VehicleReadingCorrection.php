<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VehicleReadingCorrection extends Model
{
    protected $fillable = ['tenant_id', 'division_id', 'location_id', 'vehicle_id', 'user_id', 'original_log_id', 'original_fuel_filling_id', 'corrected_log_id', 'new_km', 'new_hours', 'reason', 'verification_mode', 'effective_at', 'impacts', 'ip_address', 'user_agent'];
    protected $casts = ['effective_at' => 'datetime', 'impacts' => 'array'];

    public function vehicle() { return $this->belongsTo(Vehicle::class); }
    public function user() { return $this->belongsTo(User::class); }
    public function originalLog() { return $this->belongsTo(VehicleUpdateLog::class, 'original_log_id'); }
    /** Legacy video evidence, retained for corrections made before photo verification. */
    public function evidence() { return $this->hasOne(VehicleReadingCorrectionEvidence::class, 'correction_id'); }
    public function evidences() { return $this->hasMany(VehicleReadingCorrectionEvidence::class, 'correction_id'); }
}
