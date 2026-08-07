<?php

namespace App\Services\Banner;

use App\Enums\BannerPosition;
use App\Enums\BannerTemplateAvailability;
use App\Enums\BannerTemplateCategory;
use App\Models\BannerTemplate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class BannerTemplateLibraryService
{
    /**
     * @return Builder<BannerTemplate>
     */
    public function queryForAdmin(array $filters = []): Builder
    {
        return $this->baseQuery($filters)->availableForAdmin();
    }

    /**
     * @return Builder<BannerTemplate>
     */
    public function queryForMerchant(array $filters = []): Builder
    {
        return $this->baseQuery($filters)->availableForMerchant();
    }

    /**
     * @return Collection<int, BannerTemplate>
     */
    public function getForAdmin(array $filters = []): Collection
    {
        return $this->queryForAdmin($filters)->get();
    }

    /**
     * @return Collection<int, BannerTemplate>
     */
    public function getForMerchant(array $filters = []): Collection
    {
        return $this->queryForMerchant($filters)->get();
    }

    public function findUsableForAdmin(string $uuid): BannerTemplate
    {
        return $this->queryForAdmin()->where('uuid', $uuid)->firstOrFail();
    }

    public function findUsableForMerchant(string $uuid): BannerTemplate
    {
        return $this->queryForMerchant()->where('uuid', $uuid)->firstOrFail();
    }

    public function assertUsableForOwner(BannerTemplate $template, string $scope): void
    {
        $availability = $template->availability instanceof BannerTemplateAvailability
            ? $template->availability
            : BannerTemplateAvailability::tryFrom((string) $template->availability);

        $allowed = $scope === BannerPosition::SCOPE_ADMIN
            ? [BannerTemplateAvailability::ADMIN, BannerTemplateAvailability::BOTH]
            : [BannerTemplateAvailability::MERCHANT, BannerTemplateAvailability::BOTH];

        if ($template->trashed() || $template->status !== BannerTemplate::STATUS_ACTIVE || $availability === null || ! in_array($availability, $allowed, true)) {
            throw (new ModelNotFoundException())->setModel(BannerTemplate::class, [$template->getKey()]);
        }
    }

    /**
     * @return Builder<BannerTemplate>
     */
    private function baseQuery(array $filters = []): Builder
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $category = (string) ($filters['category'] ?? '');
        $position = (string) ($filters['default_position'] ?? ($filters['position'] ?? ''));
        $event = (string) ($filters['event'] ?? '');

        return BannerTemplate::query()
            ->withCount('banners')
            ->active()
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('name', 'like', '%'.$search.'%')
                        ->orWhere('code', 'like', '%'.$search.'%')
                        ->orWhere('default_title', 'like', '%'.$search.'%')
                        ->orWhere('default_subtitle', 'like', '%'.$search.'%');
                });
            })
            ->when(BannerTemplateCategory::tryFrom($category) !== null, fn (Builder $query) => $query->where('category', $category))
            ->when(BannerPosition::tryFrom($position) !== null, fn (Builder $query) => $query->where('default_position', $position))
            ->when($event === 'event', fn (Builder $query) => $query->whereNotNull('event_code'))
            ->when($event === 'general', fn (Builder $query) => $query->whereNull('event_code'))
            ->ordered();
    }
}
