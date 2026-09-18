<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FuelDailyCheckItem extends Model
{
    protected $fillable = [
        'fuel_daily_check_id',
        'source',
        'fuel_product_id',
        'product_name',
        'fillings_count',
        'system_liters',
        'manual_liters',
        'difference_liters',
    ];

    protected $casts = [
        'system_liters' => 'decimal:3',
        'manual_liters' => 'decimal:3',
        'difference_liters' => 'decimal:3',
    ];

    public function check()
    {
        return $this->belongsTo(FuelDailyCheck::class, 'fuel_daily_check_id');
    }
}
