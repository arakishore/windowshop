<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductReview extends Model
{
    use HasUuid, SoftDeletes;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'customer_id', 'order_id', 'order_item_id', 'product_id', 'product_variant_id',
        'rating', 'title', 'review_text', 'status', 'moderated_by', 'moderated_at',
    ];

    protected function casts(): array
    {
        return ['rating' => 'integer', 'moderated_at' => 'datetime', 'deleted_at' => 'datetime'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class)->withTrashed();
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id')->withTrashed();
    }

    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderated_by')->withTrashed();
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductReviewImage::class)->orderBy('sort_order')->orderBy('id');
    }

    public function customerDisplayName(): string
    {
        $parts = preg_split('/\s+/', trim((string) ($this->customer?->name ?: 'WindowShop Customer'))) ?: [];
        if (count($parts) < 2) {
            return $parts[0] ?? 'WindowShop Customer';
        }

        return $parts[0].' '.mb_substr(end($parts), 0, 1).'.';
    }
}
