<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LocState extends Model
{
    use SoftDeletes;

    protected $table = 'loc_states';
}
