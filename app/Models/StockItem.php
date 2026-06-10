<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockItem extends Model
{
    protected $fillable = [
        'tenant_id',
        'location_id',
        'name',
        'unit',
        'quantity',
        'brand',
        'minimum_quantity',
        'unit_cost',
        'stock_category_id',
        'active',
        'observation',
    ];

    public function category()
    {
        return $this->belongsTo(StockCategory::class, 'stock_category_id');
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function movements()
    {
        return $this->hasMany(StockMovement::class);
    }
}
