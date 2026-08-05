<?php

namespace App\Models;

use App\Enums\BannerPosition;
use App\Enums\BannerTemplateAvailability;
use App\Enums\BannerTemplateCategory;
use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BannerTemplate extends Model
{
    use HasUuid, SoftDeletes;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const CATEGORY_GENERAL = 'general';

    public const CATEGORY_FESTIVAL = 'festival';

    public const CATEGORY_SEASONAL = 'seasonal';

    public const CATEGORY_FASHION = 'fashion';

    public const CATEGORY_ELECTRONICS = 'electronics';

    public const CATEGORY_GROCERY = 'grocery';

    public const CATEGORY_SERVICES = 'services';

    public const AVAILABILITY_ADMIN = 'admin';

    public const AVAILABILITY_MERCHANT = 'merchant';

    public const AVAILABILITY_BOTH = 'both';

    protected $fillable = [
        'uuid',
        'code',
        'category',
        'name',
        'description',
        'default_title',
        'default_subtitle',
        'default_button_text',
        'desktop_image_path',
        'mobile_image_path',
        'default_position',
        'availability',
        'event_code',
        'start_offset_days',
        'end_offset_days',
        'sort_order',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'category' => BannerTemplateCategory::class,
            'availability' => BannerTemplateAvailability::class,
            'start_offset_days' => 'integer',
            'end_offset_days' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function banners(): HasMany
    {
        return $this->hasMany(Banner::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by')->withTrashed();
    }

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by')->withTrashed();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name')->orderBy('id');
    }

    public function scopeForCategory(Builder $query, BannerTemplateCategory|string $category): Builder
    {
        $value = $category instanceof BannerTemplateCategory ? $category->value : $category;

        return $query->where('category', $value);
    }

    public function scopeAvailableForAdmin(Builder $query): Builder
    {
        return $query->whereIn('availability', [
            BannerTemplateAvailability::ADMIN->value,
            BannerTemplateAvailability::BOTH->value,
        ]);
    }

    public function scopeAvailableForMerchant(Builder $query): Builder
    {
        return $query->whereIn('availability', [
            BannerTemplateAvailability::MERCHANT->value,
            BannerTemplateAvailability::BOTH->value,
        ]);
    }

    public function scopeForEvent(Builder $query, string $eventCode): Builder
    {
        return $query->where('event_code', $eventCode);
    }

    public function categoryLabel(): string
    {
        return $this->category instanceof BannerTemplateCategory
            ? $this->category->label()
            : (BannerTemplateCategory::tryFrom((string) $this->category)?->label() ?? str((string) $this->category)->headline()->toString());
    }

    public function availabilityLabel(): string
    {
        return $this->availability instanceof BannerTemplateAvailability
            ? $this->availability->label()
            : (BannerTemplateAvailability::tryFrom((string) $this->availability)?->label() ?? str((string) $this->availability)->headline()->toString());
    }

    public function positionLabel(): string
    {
        return BannerPosition::tryFrom((string) $this->default_position)?->label()
            ?? str((string) $this->default_position)->headline()->toString();
    }
}
