<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class MaintenancePhoto extends Model
{
    protected $fillable = ['tenant_id', 'division_id', 'location_id', 'maintenance_record_id',
        'uploaded_by_user_id', 'upload_token_id', 'file_path', 'original_name', 'mime_type',
        'size', 'caption', 'source'];

    public function maintenanceRecord() { return $this->belongsTo(MaintenanceRecord::class); }
    public function tenant() { return $this->belongsTo(Tenant::class); }
    public function uploader() { return $this->belongsTo(User::class, 'uploaded_by_user_id'); }
    public function uploadToken() { return $this->belongsTo(MaintenancePhotoUploadToken::class); }

    public function getUrlAttribute(): string
    {
        $diskUrl = Storage::disk('public')->url($this->file_path);

        return parse_url($diskUrl, PHP_URL_PATH) ?: $diskUrl;
    }
}
