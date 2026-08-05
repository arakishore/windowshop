<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SystemSetting extends Model
{
    use HasUuid, SoftDeletes;

    public const TYPE_STRING = 'string';

    public const TYPE_INTEGER = 'integer';

    public const TYPE_BOOLEAN = 'boolean';

    public const TYPE_JSON = 'json';

    public const TYPE_ARRAY = 'array';

    public const TYPE_TEXT = 'text';

    public const TYPE_ENCRYPTED = 'encrypted';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'uuid',
        'group_id',
        'key',
        'label',
        'value',
        'value_type',
        'is_public',
        'is_encrypted',
        'description',
        'sort_order',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
            'is_encrypted' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(SystemSettingGroup::class, 'group_id');
    }

    /**
     * @return array<int, string>
     */
    public static function valueTypes(): array
    {
        return [
            self::TYPE_STRING,
            self::TYPE_INTEGER,
            self::TYPE_BOOLEAN,
            self::TYPE_JSON,
            self::TYPE_ARRAY,
            self::TYPE_TEXT,
            self::TYPE_ENCRYPTED,
        ];
    }
}
