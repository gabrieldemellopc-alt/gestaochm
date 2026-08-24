<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Location extends Model
{
    protected $fillable = [
        'tenant_id',
        'division_id',
        'name',
'active',
        'allow_aggregated_fuel',
        'allow_aggregated_maintenance',
    ];
    protected $casts = [
        'active' => 'boolean',
        'allow_aggregated_fuel' => 'boolean',
        'allow_aggregated_maintenance' => 'boolean',
        ];
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
    public function division()
    {
        return $this->belongsTo(Division::class);
    }
        public function allocations()
    {
        return $this->hasMany(VehicleAllocation::class);
    }
}
