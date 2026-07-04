<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Ordem principal de manutencao.
 *
 * Politica oficial de custo:
 * - total_cost e o custo operacional consolidado da ordem.
 * - items.total_cost, extraCosts.amount e stockMovements.total_cost sao
 *   detalhes de composicao e nao devem ser somados novamente ao total_cost.
 * - extra_cost e campo legado/compatibilidade, nao um total independente.
 */
class MaintenanceRecord extends Model
{
    protected $fillable = [
        'tenant_id',
        'vehicle_id',
        'procedure_id',
        'maintenance_type',
        'performed_km',
        'performed_hours',
        'performed_at',
        'next_due_km',
        'next_due_hours',
        'next_due_date',
        'provider_name',
        'total_cost',
        'extra_cost',
        'reason',
        'cancelled_at',
        'cancelled_by',
        'cancel_reason',
        'notes',
        'workflow_status',
        'service_status',
        'started_at',
        'finished_at',
        'opened_by',
        'closed_by',
        'closure_notes',
    ];

    protected $casts = [
        'performed_at' => 'date',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'next_due_date' => 'date',
        'cancelled_at' => 'datetime',
    ];

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function procedure()
    {
        return $this->belongsTo(Procedure::class);
    }

    public function values()
    {
        return $this->hasMany(MaintenanceRecordValue::class);
    }

    public function canceller()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function getIsCancelledAttribute(): bool
    {
        return $this->cancelled_at !== null;
    }

    public function getKmRemainingAttribute()
    {
        if (! $this->next_due_km || ! $this->vehicle) {
            return null;
        }

        return $this->next_due_km - $this->vehicle->current_km;
    }

    public function getHoursRemainingAttribute()
    {
        if (! $this->next_due_hours || ! $this->vehicle) {
            return null;
        }

        return $this->next_due_hours - $this->vehicle->current_hours;
    }

    public function getStatusAttribute()
    {
        if ($this->km_remaining !== null) {
            if ($this->km_remaining < 0) {
                return 'danger';
            }

            if ($this->km_remaining <= 1000) {
                return 'warning';
            }

            return 'ok';
        }

        if ($this->hours_remaining !== null) {
            if ($this->hours_remaining < 0) {
                return 'danger';
            }

            if ($this->hours_remaining <= 100) {
                return 'warning';
            }

            return 'ok';
        }

        return 'ok';
    }

    public function statusLogs()
    {
        return $this->hasMany(MaintenanceRecordStatusLog::class);
    }

    public function opener()
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closer()
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function items()
    {
        return $this->hasMany(MaintenanceRecordItem::class);
    }

    public function extraCosts()
    {
        return $this->hasMany(MaintenanceRecordExtraCost::class);
    }
}
