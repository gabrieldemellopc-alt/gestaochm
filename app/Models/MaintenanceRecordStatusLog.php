<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaintenanceRecordStatusLog extends Model
{
    protected $fillable = [
        'maintenance_record_id',
        'old_status',
        'new_status',
        'changed_by',
        'reason',
    ];

    public function maintenanceRecord()
    {
        return $this->belongsTo(MaintenanceRecord::class);
    }

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
    public function user()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}