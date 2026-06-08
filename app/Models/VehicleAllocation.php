<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VehicleAllocation extends Model
{
    protected $fillable = [

        'vehicle_id',
        'division_id',
        'location_id',
        'started_at',
        'ended_at',
        'is_current'
    ];
        public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }
    
    public function division()
    {
        return $this->belongsTo(Division::class);
    }
    
    public function location()
    {
        return $this->belongsTo(Location::class);
    }
}