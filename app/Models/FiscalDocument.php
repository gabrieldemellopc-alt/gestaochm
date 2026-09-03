<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class FiscalDocument extends Model
{
    protected $guarded = [];
    protected $casts = ['issued_at' => 'datetime', 'total_amount' => 'decimal:2'];
    public function items() { return $this->hasMany(FiscalDocumentItem::class); }
    public function movements() { return $this->hasMany(StockMovement::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function location() { return $this->belongsTo(Location::class); }
    public function supplier() { return $this->belongsTo(Supplier::class); }
}
