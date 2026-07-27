<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CatalogueMasterRequest extends Model
{
    use HasUuid;

    public const TYPE_CATEGORY = 'category';
    public const TYPE_ATTRIBUTE = 'attribute';

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_NEEDS_INFO = 'needs_info';

    protected $fillable = [
        'uuid',
        'merchant_id',
        'shop_id',
        'root_product_category_id',
        'request_type',
        'suggested_name',
        'parent_product_category_id',
        'description',
        'example_product_name',
        'status',
        'admin_note',
        'requested_by',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(MerchantProfile::class, 'merchant_id');
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function rootCategory(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'root_product_category_id');
    }

    public function parentCategory(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'parent_product_category_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by')->withTrashed();
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by')->withTrashed();
    }
}
