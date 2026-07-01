<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaintenanceRecordExtraCost extends Model
{
    protected $fillable = [
        'maintenance_record_id',
        'description',
        'amount',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function maintenanceRecord()
    {
        return $this->belongsTo(MaintenanceRecord::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
