<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChecklistTemplate extends Model
{
    protected $fillable = [

        'division_id',

        'name',

        'type',

        'active'

    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */
    public function executions()
    {
        return $this->hasMany(
            ChecklistExecution::class,
            'checklist_template_id'
        );
    }
    public function division()
    {
        return $this->belongsTo(
            Division::class
        );
    }

    public function items()
    {
        return $this->hasMany(
            ChecklistTemplateItem::class
        )->orderBy('sort_order');
    }
}