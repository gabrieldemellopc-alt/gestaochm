<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VehicleUpdateLog extends Model
{
    public const READING_STATUS_VALID = 'valid';
    public const READING_STATUS_SUSPECT = 'suspect';
    public const READING_STATUS_IGNORED = 'ignored';
    public const READING_SOURCE_LABELS = [
        'initial_registration' => 'Cadastro inicial',
        'fuel' => 'Abastecimento',
        'fuel_filling' => 'Abastecimento',
        'dashboard_quick_update' => 'Atualização manual',
        'maintenance_open' => 'Abertura de OM',
        'maintenance_close' => 'Encerramento de OM',
        'manual_update' => 'Atualização manual',
        'vehicle_operation' => 'Operação do veículo',
        'operation_start' => 'Início de operação',
        'operation_end' => 'Encerramento de operação',
        'manual_tire_measurement' => 'Medição de pneus',
        'tire_measurement' => 'Medição de pneus',
        'tire_removal' => 'Troca de pneus',
        'reading_correction' => 'Correção de leitura',
        'administrative_correction' => 'Correção administrativa',
    ];
    protected $fillable = [

        'vehicle_id',
        'user_id',
        'division_id',
        'location_id',
        'type',
        'source',
        'read_at',
        'fuel_filling_id',
        'reading_status', 'reading_issue', 'reviewed_by', 'reviewed_at',
        'old_value',
        'new_value',
        'observation',

    ];

    protected $casts = [
        'read_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function fuelFilling()
    {
        return $this->belongsTo(FuelFilling::class);
    }

    public function reviewer() { return $this->belongsTo(User::class, 'reviewed_by'); }
    public function scopeUsableReading($query) { return $query->where(fn ($q) => $q->whereNull('reading_status')->orWhere('reading_status', self::READING_STATUS_VALID)); }
    public function getIsReadingUsableAttribute(): bool { return in_array($this->reading_status, [null, self::READING_STATUS_VALID], true); }
    public static function sourceLabel(?string $source): string { return self::READING_SOURCE_LABELS[$source ?? ''] ?? 'Registro de leitura'; }
    public function getSourceLabelAttribute(): string { return self::sourceLabel($this->source); }
}
