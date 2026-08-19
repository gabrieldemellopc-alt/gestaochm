<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VehicleReadingCorrectionEvidence extends Model
{
    protected $table = 'vehicle_reading_correction_evidences';
    protected $fillable = ['tenant_id', 'vehicle_id', 'correction_id', 'initiated_by', 'token_hash', 'expires_at', 'used_at', 'status', 'disk', 'path', 'original_name', 'mime_type', 'size_bytes', 'duration_seconds', 'checksum'];
    protected $hidden = ['token_hash'];
    protected $casts = ['expires_at' => 'datetime', 'used_at' => 'datetime', 'duration_seconds' => 'float'];
    public function correction() { return $this->belongsTo(VehicleReadingCorrection::class, 'correction_id'); }
    public function vehicle() { return $this->belongsTo(Vehicle::class); }
    public function isAvailable(): bool { return $this->status === 'ready' && $this->path && $this->expires_at->isFuture(); }
}
