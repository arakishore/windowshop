<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Faq extends Model
{
    use HasUuid, SoftDeletes;

    public const CATEGORY_GENERAL = 'general';

    public const CATEGORY_CUSTOMER = 'customer';

    public const CATEGORY_ORDER = 'order';

    public const CATEGORY_MERCHANT = 'merchant';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'uuid',
        'category',
        'question',
        'answer',
        'sort_order',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return array<string, string>
     */
    public static function categories(): array
    {
        return [
            self::CATEGORY_GENERAL => 'General',
            self::CATEGORY_CUSTOMER => 'Customer',
            self::CATEGORY_ORDER => 'Order',
            self::CATEGORY_MERCHANT => 'Merchant',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function orderedCategoryKeys(): array
    {
        return [
            self::CATEGORY_GENERAL,
            self::CATEGORY_CUSTOMER,
            self::CATEGORY_ORDER,
            self::CATEGORY_MERCHANT,
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public function scopeOfCategory($query, string $category)
    {
        return $query->where('category', $category);
    }
}
