<?php

namespace App\Models;

use App\Services\StockItemNormalizer;
use Illuminate\Database\Eloquent\Model;

class StockItemAlias extends Model
{
    protected $fillable = [
        'stock_item_id',
        'alias',
        'source',
        'created_by',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $alias): void {
            $alias->normalized_alias = app(StockItemNormalizer::class)
                ->normalizeName((string) $alias->alias);
        });
    }

    public function stockItem()
    {
        return $this->belongsTo(StockItem::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
