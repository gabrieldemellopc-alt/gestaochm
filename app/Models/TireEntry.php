<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TireEntry extends Model
{
    protected $fillable = [
        'tenant_id',
        'entry_date',
        'supplier_name',
        'invoice_number',
        'quantity',
        'unit_cost',
        'total_cost',
        'brand',
        'model',
        'size',
        'initial_tread_depth',
        'code_prefix',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'entry_date' => 'date',
        'unit_cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'initial_tread_depth' => 'decimal:2',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function items()
    {
        return $this->hasMany(TireEntryItem::class);
    }

    public function tires()
    {
        return $this->belongsToMany(
            Tire::class,
            'tire_entry_items',
            'tire_entry_id',
            'tire_id'
        );
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}