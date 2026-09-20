<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductReviewImage extends Model
{
    use HasUuid;

    protected $fillable = [
        'uuid',
        'product_review_id',
        'image_path',
        'thumbnail_path',
        'sort_order',
    ];

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    public function review(): BelongsTo
    {
        return $this->belongsTo(ProductReview::class, 'product_review_id');
    }
}
