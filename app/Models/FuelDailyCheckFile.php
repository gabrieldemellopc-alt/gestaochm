<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FuelDailyCheckFile extends Model
{
    protected $fillable = [
        'fuel_daily_check_id',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size_bytes',
        'source',
        'document_type',
        'document_date',
        'invoice_number',
        'supplier_id',
        'supplier_name',
        'supplier_document',
        'uploaded_by',
    ];

    protected $casts = [
        'document_date' => 'date',
    ];

    public function check()
    {
        return $this->belongsTo(FuelDailyCheck::class, 'fuel_daily_check_id');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function receipts()
    {
        return $this->belongsToMany(
            FuelReceipt::class,
            'fuel_daily_check_file_receipt',
            'fuel_daily_check_file_id',
            'fuel_receipt_id'
        )->withTimestamps();
    }
}
