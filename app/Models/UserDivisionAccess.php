<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserDivisionAccess extends Model
{
    protected $fillable = [

        'user_id',
        'division_id',
        'module',
        'profile',
        'location_id',
        'tenant_id',
        'active'

    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    public function user()
    {
        return $this->belongsTo(
            User::class
        );
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