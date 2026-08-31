<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromotionTarget extends Model
{
    public const ROLE_ELIGIBLE = 'eligible';
    public const ROLE_BUY = 'buy';
    public const ROLE_GET = 'get';
    public const ROLE_GIFT = 'gift';

    public const TYPE_ALL = 'all';
    public const TYPE_PRODUCT = 'product';
    public const TYPE_VARIANT = 'variant';
    public const TYPE_CATEGORY = 'category';
    public const TYPE_BRAND = 'brand';
    public const TYPE_COLLECTION = 'collection';

    protected $fillable = [
        'promotion_id',
        'target_role',
        'target_type',
        'target_id',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'target_id' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }
}
