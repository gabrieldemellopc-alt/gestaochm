<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FuelDailyCheckUploadToken extends Model
{
    protected $fillable = [
        'fuel_daily_check_id',
        'token_hash',
        'created_by',
        'expires_at',
        'last_used_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'last_used_at' => 'datetime',
    ];

    public function check()
    {
        return $this->belongsTo(FuelDailyCheck::class, 'fuel_daily_check_id');
    }

    public function isValid(): bool
    {
        return $this->expires_at && $this->expires_at->isFuture();
    }
}
