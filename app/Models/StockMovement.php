<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    protected $fillable = [
        'tenant_id',

        'stock_item_id',

        'movement_type',

        'quantity',

        'unit_cost',

        'description'
    ];
}