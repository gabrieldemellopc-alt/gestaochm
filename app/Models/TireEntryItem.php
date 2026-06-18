<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TireEntryItem extends Model
{
    protected $fillable = [
        'tire_entry_id',
        'tire_id',
        'cancelled_at',
        'cancelled_by',
        'cancel_reason',
    ];

    protected $casts = [
        'cancelled_at' => 'datetime',
    ];

    public function entry()
    {
        return $this->belongsTo(TireEntry::class, 'tire_entry_id');
    }

    public function tire()
    {
        return $this->belongsTo(Tire::class);
    }

    public function canceller()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function getIsCancelledAttribute(): bool
    {
        return $this->cancelled_at !== null;
    }
}
