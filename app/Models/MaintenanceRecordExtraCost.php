<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Detalhe de custo avulso da ordem.
 *
 * amount e composicao da manutencao. Quando a ordem usa total_cost, nao some
 * este valor novamente em indicadores consolidados.
 */
class MaintenanceRecordExtraCost extends Model
{
    protected $fillable = [
        'maintenance_record_id',
        'description',
        'amount',
        'cost_date',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'cost_date' => 'date',
    ];

    public function maintenanceRecord()
    {
        return $this->belongsTo(MaintenanceRecord::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getEffectiveCostDateAttribute()
    {
        return $this->cost_date ?? $this->created_at;
    }
}
