<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TireEntryItem extends Model
{
    protected $fillable = [
        'tire_entry_id',
        'tire_id',
    ];

    public function entry()
    {
        return $this->belongsTo(TireEntry::class, 'tire_entry_id');
    }

    public function tire()
    {
        return $this->belongsTo(Tire::class);
    }
}