<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChecklistExecution extends Model
{
    protected $fillable = [

        'division_id',
        'vehicle_id',
        'checklist_template_id',
        'user_id',
        'status',
        'notes',
        'executed_at'

    ];

    protected $casts = [

        'executed_at' => 'datetime'

    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    public function vehicle()
    {
        return $this->belongsTo(
            Vehicle::class
        );
    }

    public function template()
    {
        return $this->belongsTo(
            ChecklistTemplate::class,
            'checklist_template_id'
        );
    }

    public function user()
    {
        return $this->belongsTo(
            User::class
        );
    }

    public function answers()
    {
        return $this->hasMany(
            ChecklistExecutionAnswer::class
        );
    }
}