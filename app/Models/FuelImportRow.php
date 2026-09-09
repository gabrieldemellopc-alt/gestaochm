<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FuelImportRow extends Model
{
    protected $fillable = ['fuel_import_batch_id', 'row_number', 'sequence', 'external_reference', 'payload', 'status', 'entity_type', 'entity_id', 'error'];

    protected $casts = ['payload' => 'array'];

    public function batch()
    {
        return $this->belongsTo(FuelImportBatch::class, 'fuel_import_batch_id');
    }
}
