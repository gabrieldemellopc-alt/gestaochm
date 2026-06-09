<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TireRetread extends Model
{
    protected $fillable = [
        'tenant_id',
        'tire_id',
        'retreaded_at',
        'new_tread_depth',
        'previous_tread_reference',
        'provider_name',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'retreaded_at' => 'date',
        'new_tread_depth' => 'decimal:2',
        'previous_tread_reference' => 'decimal:2',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function tire()
    {
        return $this->belongsTo(Tire::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
