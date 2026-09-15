<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Vehicle extends Model
{
    public const METER_STATUS_NORMAL = 'normal';
    public const METER_STATUS_FAULTY = 'faulty';
    public const METER_STATUS_UNRELIABLE = 'unreliable';
    public const METER_STATUSES = [self::METER_STATUS_NORMAL, self::METER_STATUS_FAULTY, self::METER_STATUS_UNRELIABLE];
    public const FLEET_RELATION_INTERNAL = 'internal';
    public const FLEET_RELATION_AGGREGATED = 'aggregated';
    public const FLEET_RELATION_RENTED = 'rented';

    public const TYPES = [
        'automovel' => ['label' => 'Automóvel', 'icon' => 'automovel.png'],
        'caminhonete' => ['label' => 'Caminhonete', 'icon' => 'caminhonete.png'],
        'onibus' => ['label' => 'Ônibus', 'icon' => 'onibus.png'],
        'lixo' => ['label' => 'Caminhão de lixo / Compactador', 'icon' => 'lixo.png'],
        'cacamba' => ['label' => 'Caçamba', 'icon' => 'cacamba.png'],
        'bau' => ['label' => 'Baú', 'icon' => 'bau.png'],
        'pipa' => ['label' => 'Caminhão pipa', 'icon' => 'pipa.png'],
        'carroceria_aberta' => ['label' => 'Carroceria aberta', 'icon' => 'carroceria_aberta.png'],
        'prancha' => ['label' => 'Prancha', 'icon' => 'prancha.png'],
        'trator' => ['label' => 'Trator', 'icon' => 'trator.png'],
        'retroescavadeira' => ['label' => 'Retroescavadeira', 'icon' => 'retro.png'],
        'varredeira' => ['label' => 'Varredeira', 'icon' => 'bobcat.png'],
    ];

    public static function typeOptions(): array { return self::TYPES; }
    public static function typeValues(): array { return array_keys(self::TYPES); }
    public static function iconForType(?string $type): string { return self::TYPES[$type]['icon'] ?? self::TYPES['automovel']['icon']; }

    protected $fillable = [
        'tenant_id',
        'name',
        'plate',
        'renavam',
        'serial_number',
        'chassis',
        'brand',
        'model',
        'year',
        'type',
        'fleet_relation',
        'location_id',
        'division_id',
        'current_km',
        'current_hours',
        'status',
        'operation_started_at',
        'operational_status',
        'notes',
        'tire_layout',
        'asset_code',
        'last_km_update_at',
        'last_hours_update_at',
        'km_control_enabled',
        'hours_control_enabled',
        'tire_control_enabled',
        'km_meter_status',
        'hours_meter_status',
    ];
    protected $casts = [
        'status_changed_at' => 'datetime',  
        'operation_started_at' => 'date',    
        'km_control_enabled' => 'boolean',
        'hours_control_enabled' => 'boolean',
        'tire_control_enabled' => 'boolean',
    ];
    
    protected $attributes = [
        'operational_status' => 'operational',
        'km_control_enabled' => true,
        'hours_control_enabled' => false,
        'tire_control_enabled' => true,
    ];
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
    public function fuelProducts(){ return $this->belongsToMany(FuelProduct::class, 'fuel_product_vehicle')->withTimestamps(); }
    public function checklistExecutions()
    {
        return $this->hasMany(
            ChecklistExecution::class
        );
    }
    public function allocations()
    {
        return $this->hasMany(VehicleAllocation::class);
    }
    public function procedures()
    {
        return $this->belongsToMany(
            Procedure::class
        );
    }
    public function maintenances()
    {
        return $this->hasMany(MaintenanceRecord::class);
    }
    public function validMaintenances()
    {
        return $this->hasMany(MaintenanceRecord::class)
            ->whereNull('cancelled_at');
    }
    public function currentAllocation()
    {
        return $this->hasOne(
            VehicleAllocation::class
        )->where('is_current', true);
    }
    public function activeMaintenances()
    {
        return $this->hasMany(
            MaintenanceRecord::class
        )
        ->whereNull('cancelled_at')
        ->latest();
    }
    public function division()
    {
        return $this->belongsTo(Division::class);
    }
    
    public function location()
    {
        return $this->belongsTo(Location::class);
    }
    public function getTypeIconAttribute()
    {
        return self::iconForType($this->type);
    }
    public function updateLogs()
    {
        return $this->hasMany(
            VehicleUpdateLog::class
        )
        ->latest();
    }
     public function tirePositions()
    {
        return $this->hasMany(VehicleTirePosition::class);
    }
    
    public function tireInstallations()
    {
        return $this->hasMany(TireInstallation::class);
    }
    
    public function activeTireInstallations()
    {
        return $this->hasMany(TireInstallation::class)
            ->where('active', true);
    }
    
    public function tireMeasurements()
    {
        return $this->hasMany(TireMeasurement::class);
    }   
    public function operations()
    {
        return $this->hasMany(\App\Models\VehicleOperation::class);
    }
    
    public function openOperation()
    {
        return $this->hasOne(\App\Models\VehicleOperation::class)
            ->where('status', 'open')
            ->latestOfMany();
    }
}
