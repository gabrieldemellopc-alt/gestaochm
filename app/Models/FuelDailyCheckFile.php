<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FuelDailyCheckFile extends Model
{
    protected $fillable = [
        'fuel_daily_check_id',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size_bytes',
        'source',
        'uploaded_by',
    ];

    public function check()
    {
        return $this->belongsTo(FuelDailyCheck::class, 'fuel_daily_check_id');
    }
}
