<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemAuditLog extends Model
{
    protected $fillable = [
        'tenant_id',
        'division_id',
        'location_id',
        'user_id',
        'user_profile',
        'auditable_type',
        'auditable_id',
        'module',
        'action',
        'summary',
        'before_data',
        'after_data',
        'metadata',
        'reason',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'before_data' => 'array',
        'after_data' => 'array',
        'metadata' => 'array',
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
}
