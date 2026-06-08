<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Division extends Model
{
    protected $fillable = [

        'tenant_id',
        'name',
        'logo',
        'logo_theme',
        'primary_color',
    ];
    public function locations()
    {
        return $this->hasMany(Location::class);
    }
    public function allocations()
    {
        return $this->hasMany(VehicleAllocation::class);
    }
    public function users()
    {
        return $this->belongsToMany(User::class);
    }
    public function userAccesses()
    {
        return $this->hasMany(
            UserDivisionAccess::class
        );
    }

}