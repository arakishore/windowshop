<?php

namespace App\Http\Requests\Concerns;

use App\Enums\BannerLinkType;
use App\Enums\BannerPosition;
use App\Models\Banner;
use App\Models\Shop;
use App\Services\Banner\BannerLinkResolver;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

trait ValidatesBannerRequest
{
    /**
     * @return array<string, mixed>
     */
    protected function bannerRules(bool $creating): array
    {
        return [
            'position' => ['required', Rule::enum(BannerPosition::class)],
            'title' => ['required', 'string', 'max:180'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'desktop_image' => [$creating ? 'required' : 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'mobile_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove_mobile_image' => ['nullable', 'boolean'],
            'link_type' => ['nullable', Rule::enum(BannerLinkType::class)],
            'link_value' => ['nullable', 'string', 'max:255'],
            'open_in_new_tab' => ['nullable', 'boolean'],
            'button_text' => ['nullable', 'string', 'max:80'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'status' => ['required', Rule::in([Banner::STATUS_ACTIVE, Banner::STATUS_INACTIVE])],
        ];
    }

    protected function validateBannerBusinessRules(Validator $validator, string $scope, ?int $merchantId, ?int $shopId, ?Banner $ignore = null): void
    {
        $validator->after(function (Validator $validator) use ($scope, $merchantId, $shopId, $ignore): void {
            $position = BannerPosition::tryFrom((string) $this->input('position'));
            $linkType = BannerLinkType::tryFrom((string) $this->input('link_type', BannerLinkType::NONE->value)) ?: BannerLinkType::NONE;

            if ($position === null) {
                return;
            }

            if ($position->scope() !== $scope) {
                $validator->errors()->add('position', 'This banner position is not available for the selected owner.');
            }

            if ($merchantId !== null && $shopId !== null) {
                $shopBelongsToMerchant = Shop::query()
                    ->whereKey($shopId)
                    ->where('merchant_id', $merchantId)
                    ->whereNull('deleted_at')
                    ->exists();

                if (! $shopBelongsToMerchant) {
                    $validator->errors()->add('shop_id', 'The selected shop must belong to the selected merchant.');
                }
            }

            if (! app(BannerLinkResolver::class)->targetExists($linkType, $this->input('link_value'), $merchantId, $shopId)) {
                $validator->errors()->add('link_value', 'The banner link target is not valid for the selected link type.');
            }

            if ($this->input('status') === Banner::STATUS_ACTIVE && $this->activeOrScheduledCount($position, $merchantId, $shopId, $ignore) >= $position->maxBanners()) {
                $validator->errors()->add('position', 'The maximum active or scheduled banners for this position has been reached.');
            }
        });
    }

    protected function activeOrScheduledCount(BannerPosition $position, ?int $merchantId, ?int $shopId, ?Banner $ignore): int
    {
        return Banner::query()
            ->where('position', $position->value)
            ->where('status', Banner::STATUS_ACTIVE)
            ->where(function ($query): void {
                $query->whereNull('ends_at')->orWhere('ends_at', '>=', now(config('app.timezone')));
            })
            ->when($merchantId === null, fn ($query) => $query->whereNull('merchant_id'), fn ($query) => $query->where('merchant_id', $merchantId))
            ->when($shopId === null, fn ($query) => $query->whereNull('shop_id'), fn ($query) => $query->where('shop_id', $shopId))
            ->when($ignore !== null, fn ($query) => $query->whereKeyNot($ignore->getKey()))
            ->count();
    }

    /**
     * @return array<string, mixed>
     */
    public function bannerData(): array
    {
        $data = $this->validated();
        $linkType = $data['link_type'] ?? BannerLinkType::NONE->value;

        return [
            'position' => $data['position'],
            'title' => trim($data['title']),
            'subtitle' => $this->nullableString($data['subtitle'] ?? null),
            'description' => $this->nullableString($data['description'] ?? null),
            'link_type' => $linkType,
            'link_value' => $linkType === BannerLinkType::NONE->value ? null : $this->nullableString($data['link_value'] ?? null),
            'open_in_new_tab' => $linkType === BannerLinkType::CUSTOM_URL->value && (bool) ($data['open_in_new_tab'] ?? false),
            'button_text' => $this->nullableString($data['button_text'] ?? null),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'status' => $data['status'],
        ];
    }

    protected function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }
}
