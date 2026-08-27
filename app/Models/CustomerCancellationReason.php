<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerCancellationReason extends Model
{
    use HasUuid, SoftDeletes;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    public const CODE_OTHER = 'other';

    protected $fillable = [
        'uuid',
        'code',
        'name',
        'requires_comment',
        'sort_order',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'requires_comment' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE)->whereNull('deleted_at');
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * @return array<int, string>
     */
    public static function statuses(): array
    {
        return [self::STATUS_ACTIVE, self::STATUS_INACTIVE];
    }

    /**
     * @return array<string, array{name: string, requires_comment: bool, sort_order: int, status: string}>
     */
    public static function defaults(): array
    {
        return [
            'ordered_by_mistake' => ['name' => 'Ordered by mistake', 'requires_comment' => false, 'sort_order' => 10, 'status' => self::STATUS_ACTIVE],
            'want_to_change_items' => ['name' => 'Want to change items', 'requires_comment' => false, 'sort_order' => 20, 'status' => self::STATUS_ACTIVE],
            'want_to_place_different_order' => ['name' => 'Want to place a different order', 'requires_comment' => false, 'sort_order' => 30, 'status' => self::STATUS_ACTIVE],
            'found_another_product' => ['name' => 'Found another product', 'requires_comment' => false, 'sort_order' => 40, 'status' => self::STATUS_ACTIVE],
            'no_longer_needed' => ['name' => 'No longer needed', 'requires_comment' => false, 'sort_order' => 50, 'status' => self::STATUS_ACTIVE],
            self::CODE_OTHER => ['name' => 'Other', 'requires_comment' => true, 'sort_order' => 999, 'status' => self::STATUS_ACTIVE],
        ];
    }
}
