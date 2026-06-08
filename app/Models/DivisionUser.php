<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DivisionUser extends Model
{
    protected $table = 'division_user';

    protected $fillable = [

        'division_id',

        'user_id'

    ];

    public $timestamps = false;
}