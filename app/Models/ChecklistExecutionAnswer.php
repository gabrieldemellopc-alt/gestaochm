<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChecklistExecutionAnswer extends Model
{
    protected $fillable = [

        'checklist_execution_id',
        'checklist_template_item_id',
        'answer',
        'photo_path'

    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    public function execution()
    {
        return $this->belongsTo(
            ChecklistExecution::class,
            'checklist_execution_id'
        );
    }

    public function item()
    {
        return $this->belongsTo(
            ChecklistTemplateItem::class,
            'checklist_template_item_id'
        );
    }
}