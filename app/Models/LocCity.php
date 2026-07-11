<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LocCity extends Model
{
    use SoftDeletes;

    protected $table = 'loc_cities';
}
