<?php

namespace App\Models;
use App\Models\ChecklistTemplate;

use Illuminate\Database\Eloquent\Model;

class DailyChecklist extends Model
{
    protected $fillable = [
        'tenant_id',
        'division_id',
        'location_id',
        'user_id',
        'vehicle_id',
        'checklist_template_id',
        'module',
        'profile',
        'checklist_date',
        'status',
        'notes',
        'completed_at',
    ];

    protected $casts = [
        'checklist_date' => 'date',
        'completed_at' => 'datetime',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function division()
    {
        return $this->belongsTo(Division::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function template()
    {
        return $this->belongsTo(ChecklistTemplate::class, 'checklist_template_id');
    }
    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function items()
    {
        return $this->hasMany(DailyChecklistItem::class);
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }
}