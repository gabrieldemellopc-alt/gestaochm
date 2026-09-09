<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FuelImportBatch extends Model
{
    protected $fillable = ['tenant_id', 'division_id', 'location_id', 'fuel_tank_id', 'responsible_user_id', 'source_file', 'source_hash', 'status', 'is_historical_import', 'allow_legacy_balance_anomalies', 'summary'];

    protected $casts = ['is_historical_import' => 'boolean', 'allow_legacy_balance_anomalies' => 'boolean', 'summary' => 'array'];

    public function rows()
    {
        return $this->hasMany(FuelImportRow::class);
    }
}
