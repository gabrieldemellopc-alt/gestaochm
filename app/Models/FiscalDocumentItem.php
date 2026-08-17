<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class FiscalDocumentItem extends Model { protected $guarded = []; public function stockItem() { return $this->belongsTo(StockItem::class); } public function stockMovement() { return $this->belongsTo(StockMovement::class); } public function category() { return $this->belongsTo(StockCategory::class, 'stock_category_id'); } }
