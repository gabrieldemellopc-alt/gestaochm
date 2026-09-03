<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class SupplierAlias extends Model { protected $fillable=['supplier_id','alias','normalized_alias']; public function supplier(){return $this->belongsTo(Supplier::class);} }
