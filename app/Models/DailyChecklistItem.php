<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyChecklistItem extends Model
{
    protected $fillable = [
        'daily_checklist_id',
        'key',
        'label',
        'checked',
        'notes',
    ];

    protected $casts = [
        'checked' => 'boolean',
    ];

    public function checklist()
    {
        return $this->belongsTo(DailyChecklist::class, 'daily_checklist_id');
    }
}