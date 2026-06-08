<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\ProcedureField;
class Procedure extends Model
{
    protected $fillable = [

        'tenant_id',

        'name',

        'validity_type',

        'interval_km',

        'interval_hours',

        'interval_days',

        'can_be_internal',

        'color',
        'validity_km',
        'validity_hours',
        'validity_period',

        'icon'
    ];
    public function fields()
    {
        return $this->hasMany(ProcedureField::class)
            ->orderBy('sort_order');
    }
    public function vehicles()
    {
        return $this->belongsToMany(
    
            Vehicle::class
    
        );
    }
    public function maintenances()
    {
        return $this->hasMany(MaintenanceRecord::class);
    }
}