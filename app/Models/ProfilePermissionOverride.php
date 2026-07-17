<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProfilePermissionOverride extends Model
{
    protected $fillable = [
        'tenant_id',
        'division_id',
        'location_id',
        'module',
        'profile',
        'permission_key',
        'allowed',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'allowed' => 'boolean',
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

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}