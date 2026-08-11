<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaintenancePhotoUploadToken extends Model
{
    protected $fillable = ['maintenance_record_id', 'token', 'created_by', 'expires_at', 'used_at', 'revoked_at', 'max_uploads'];
    protected $casts = ['expires_at' => 'datetime', 'used_at' => 'datetime', 'revoked_at' => 'datetime'];
    protected $hidden = ['token'];

    public function maintenanceRecord() { return $this->belongsTo(MaintenanceRecord::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function photos() { return $this->hasMany(MaintenancePhoto::class, 'upload_token_id'); }
    public function isExpired(): bool { return $this->expires_at->isPast(); }
    public function isRevoked(): bool { return $this->revoked_at !== null; }
    public function canReceiveUploads(int $incoming = 0): bool
    {
        return ! $this->isExpired() && ! $this->isRevoked()
            && $this->maintenanceRecord?->workflow_status === 'open'
            && ! $this->maintenanceRecord?->cancelled_at
            && ($this->max_uploads === null || $this->photos()->count() + $incoming <= $this->max_uploads);
    }
}
