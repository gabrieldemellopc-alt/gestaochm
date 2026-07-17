<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FuelFilling extends Model
{
    public const SOURCE_INTERNAL_TANK = 'internal_tank';
    public const SOURCE_EXTERNAL_STATION = 'external_station';
    protected $fillable = [
        'tenant_id',
        'division_id',
        'location_id',
        'fuel_tank_id',
        'fuel_product_id',
        'source',
        'vehicle_id',
        'driver_id',
        'filled_at',
        'vehicle_km',
        'vehicle_hours',
        'quantity_liters',
        'unit_cost',
        'total_cost',
        'supplier_name',
        'document_number',
        'responsible_user_id',
        'notes',
        'cancelled_at',
        'cancelled_by',
        'cancel_reason',
    ];

    protected $casts = [
        'filled_at' => 'datetime',
        'vehicle_km' => 'decimal:2',
        'vehicle_hours' => 'decimal:2',
        'quantity_liters' => 'decimal:3',
        'unit_cost' => 'decimal:4',
        'total_cost' => 'decimal:2',
        'cancelled_at' => 'datetime',
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

    public function tank()
    {
        return $this->belongsTo(FuelTank::class, 'fuel_tank_id');
    }

    public function product()
    {
        return $this->belongsTo(FuelProduct::class, 'fuel_product_id');
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function responsible()
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function responsibleUser()
    {
        return $this->responsible();
    }

    public function canceller()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }
    public function getResolvedSourceAttribute(): string
    {
        return $this->source ?: self::SOURCE_INTERNAL_TANK;
    }

    public function getIsExternalAttribute(): bool
    {
        return $this->resolved_source === self::SOURCE_EXTERNAL_STATION;
    }

    public function getSourceLabelAttribute(): string
    {
        return \App\Support\ChmLabel::for('fuel_filling_source', $this->resolved_source);
    }

    public function getLocationLabelAttribute(): string
    {
        if ($this->is_external) {
            $supplier = trim((string) $this->supplier_name);
            $document = trim((string) $this->document_number);

            if ($supplier !== '' && $document !== '') {
                return "{$supplier} ({$document})";
            }

            if ($supplier !== '') {
                return $supplier;
            }

            if ($document !== '') {
                return "Documento {$document}";
            }

            return 'Posto não informado';
        }

        return $this->tank?->name ?? 'Tanque da unidade';
    }
}