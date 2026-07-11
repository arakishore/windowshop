<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LocCountry extends Model
{
    use SoftDeletes;

    protected $table = 'loc_countries';
}
