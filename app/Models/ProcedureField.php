<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProcedureField extends Model
{
    protected $fillable = [

        'procedure_id',

        'label',

        'slug',

        'field_type',

        'required',

        'options',
        'stock_category_id',
        'has_quantity',
        'sort_order'
    ];

    protected $casts = [

        'options' => 'array',

        'required' => 'boolean'
    ];
    public function stockCategory()
    {
        return $this->belongsTo(
            StockCategory::class
        );
    }
}