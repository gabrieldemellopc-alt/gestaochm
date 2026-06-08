<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockCategory extends Model
{
    protected $fillable = [

        'tenant_id',

        'name'
    ];

    public function items()
    {
        return $this->hasMany(
            StockItem::class
        );
    }
}