<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChecklistTemplateItem extends Model
{
    protected $fillable = [

        'checklist_template_id',

        'label',

        'field_type',

        'required',
'is_active',
        'sort_order'

    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */
    public function answers()
    {
        return $this->hasMany(
            ChecklistExecutionAnswer::class,
            'checklist_template_item_id'
        );
    }
    public function template()
    {
        return $this->belongsTo(
            ChecklistTemplate::class,
            'checklist_template_id'
        );
    }
}