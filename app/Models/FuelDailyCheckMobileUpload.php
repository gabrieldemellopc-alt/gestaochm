<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FuelDailyCheckMobileUpload extends Model
{
    protected $fillable = [
        'fuel_daily_check_upload_token_id',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size_bytes',
    ];

    public function token()
    {
        return $this->belongsTo(
            FuelDailyCheckUploadToken::class,
            'fuel_daily_check_upload_token_id'
        );
    }
}
