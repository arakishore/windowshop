<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminSetting extends Model
{
    public const TYPE_BOOLEAN = 'boolean';
    public const TYPE_DECIMAL = 'decimal';
    public const TYPE_INTEGER = 'integer';
    public const TYPE_JSON = 'json';
    public const TYPE_STRING = 'string';

    protected $fillable = [
        'group',
        'setting_key',
        'setting_value',
        'setting_type',
    ];
}
