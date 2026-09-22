<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FuelReceipt extends Model
{
    protected $fillable = [
        'tenant_id',
        'division_id',
        'location_id',
        'fuel_tank_id',
        'fuel_product_id',
        'received_at',
        'quantity_liters',
        'unit_cost',
        'total_cost',
        'supplier_name',
        'supplier_id',
        'supplier_document',
        'invoice_number',
        'invoice_date',
        'invoice_pending',
        'replaces_receipt_id',
        'replaced_by_receipt_id',
        'responsible_user_id',
        'notes',
        'cancelled_at',
        'cancelled_by',
        'cancel_reason',
    ];

    protected $casts = [
        'received_at' => 'datetime',
        'quantity_liters' => 'decimal:3',
        'unit_cost' => 'decimal:4',
        'total_cost' => 'decimal:2',
        'invoice_date' => 'date',
        'invoice_pending' => 'boolean',
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

    public function dailyCheckFiles()
    {
        return $this->belongsToMany(
            FuelDailyCheckFile::class,
            'fuel_daily_check_file_receipt',
            'fuel_receipt_id',
            'fuel_daily_check_file_id'
        )->withTimestamps();
    }

    public function invoiceFiles()
    {
        return $this->dailyCheckFiles()
            ->where(
                'fuel_daily_check_files.document_type',
                'fuel_invoice'
            );
    }

    public function replacesReceipt()
    {
        return $this->belongsTo(
            self::class,
            'replaces_receipt_id'
        );
    }

    public function replacedByReceipt()
    {
        return $this->belongsTo(
            self::class,
            'replaced_by_receipt_id'
        );
    }
    public function supplier(){return $this->belongsTo(Supplier::class);}
}
