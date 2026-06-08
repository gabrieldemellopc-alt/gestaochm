<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaintenanceRecord extends Model
{
    protected $fillable = [

        'tenant_id',

        'vehicle_id',

        'procedure_id',

        'maintenance_type',

        'performed_km',

        'performed_hours',

        'performed_at',

        'next_due_km',

        'next_due_hours',

        'next_due_date',
        'provider_name',
        'total_cost',
        'extra_cost',
        'reason',

        'notes'
    ];
    protected $casts = [
    
        'performed_at' => 'date',
    
        'next_due_date' => 'date'
    ];
    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }
    
    public function procedure()
    {
        return $this->belongsTo(Procedure::class);
    }
    public function values()
    {
        return $this->hasMany(MaintenanceRecordValue::class);
    }
    public function getKmRemainingAttribute()
    {
        if (
            !$this->next_due_km ||
            !$this->vehicle
        ) {
            return null;
        }
    
        return
            $this->next_due_km -
            $this->vehicle->current_km;
    }
    public function getHoursRemainingAttribute()
    {
        if (
            !$this->next_due_hours ||
            !$this->vehicle
        ) {
            return null;
        }
    
        return
            $this->next_due_hours -
            $this->vehicle->current_hours;
    }
    public function getStatusAttribute()
    {
        if ($this->km_remaining !== null) {
    
            if ($this->km_remaining < 0) {
                return 'danger';
            }
    
            if ($this->km_remaining <= 1000) {
                return 'warning';
            }
    
            return 'ok';
        }
    
        if ($this->hours_remaining !== null) {
    
            if ($this->hours_remaining < 0) {
                return 'danger';
            }
    
            if ($this->hours_remaining <= 100) {
                return 'warning';
            }
    
            return 'ok';
        }
    
        return 'ok';
    }
    
}