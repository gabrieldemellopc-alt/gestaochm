<?php

namespace App\Models;

use App\Services\StockItemNormalizer;
use Illuminate\Database\Eloquent\Model;

class StockItem extends Model
{
    protected $casts = [
        'is_workshop_consumable' => 'boolean',
    ];

    protected $fillable = [
        'tenant_id',
        'location_id',
        'name',
        'unit',
        'quantity',
        'brand',
        'minimum_quantity',
        'unit_cost',
        'stock_category_id',
        'direct_purchase_entry_movement_id',
        'active',
        'is_workshop_consumable',
        'observation',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $item): void {
            $item->normalized_name = app(StockItemNormalizer::class)
                ->normalizeName((string) $item->name);
        });
    }

    public function category()
    {
        return $this->belongsTo(StockCategory::class, 'stock_category_id');
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function movements()
    {
        return $this->hasMany(StockMovement::class);
    }

    public function aliases()
    {
        return $this->hasMany(StockItemAlias::class);
    }

    public function procedures()
    {
        return $this->belongsToMany(Procedure::class, 'procedure_stock_items')
            ->withTimestamps();
    }
}
