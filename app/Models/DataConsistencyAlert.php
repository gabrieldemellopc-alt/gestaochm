<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class DataConsistencyAlert extends Model
{
    public const STATUSES = ['new', 'reviewing', 'resolved', 'ignored', 'archived'];
    public const SEVERITIES = ['critical', 'warning', 'review', 'info'];
    protected $fillable = ['tenant_id','division_id','location_id','module','rule_key','severity','context_type','title','summary','details','entity_type','entity_id','related_entity_type','related_entity_id','fingerprint','status','reviewed_by','reviewed_at','resolution_note','first_detected_at','last_detected_at','last_scanned_at','resolved_at','ignored_at','archived_at'];
    protected $casts = ['details'=>'array','reviewed_at'=>'datetime','first_detected_at'=>'datetime','last_detected_at'=>'datetime','last_scanned_at'=>'datetime','resolved_at'=>'datetime','ignored_at'=>'datetime','archived_at'=>'datetime'];
    public function reviewer() { return $this->belongsTo(User::class, 'reviewed_by'); }
    public function location() { return $this->belongsTo(Location::class); }
}
