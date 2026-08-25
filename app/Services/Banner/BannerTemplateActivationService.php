<?php

namespace App\Services\Banner;

use App\Enums\BannerLinkType;
use App\Enums\BannerPosition;
use App\Enums\BannerSourceType;
use App\Models\Banner;
use App\Models\BannerTemplate;
use App\Models\MerchantProfile;
use App\Models\Shop;
use App\Models\User;
use App\Services\DateTime\BusinessTimeService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BannerTemplateActivationService
{
    public const RESET_IMAGES_ONLY = 'images_only';
    public const RESET_TEXT = 'text';
    public const RESET_ALL = 'all';

    public function __construct(
        private readonly BannerLimitService $limits,
        private readonly BannerTemplateLibraryService $library,
        private readonly BannerImageService $images,
    ) {}

    public function createAdminBannerFromTemplate(BannerTemplate $template, array $data, User $actor): Banner
    {
        $ownerType = (string) ($data['owner_type'] ?? 'marketplace');
        $scope = $ownerType === 'merchant' ? BannerPosition::SCOPE_MERCHANT : BannerPosition::SCOPE_ADMIN;

        $this->library->assertUsableForOwner($template, $scope);
        $this->assertPositionCompatible($data['position'] ?? $template->default_position, $scope);

        return DB::transaction(function () use ($template, $data, $actor, $ownerType): Banner {
            return Banner::query()->create([
                ...$this->templatePayload($template, $data),
                'merchant_id' => $ownerType === 'merchant' ? (int) $data['merchant_id'] : null,
                'shop_id' => $ownerType === 'merchant' ? (int) $data['shop_id'] : null,
                'created_by' => $actor->getKey(),
                'updated_by' => $actor->getKey(),
            ]);
        });
    }

    public function createMerchantBannerFromTemplate(BannerTemplate $template, MerchantProfile $merchant, Shop $shop, array $data, User $actor): Banner
    {
        abort_unless((int) $shop->merchant_id === (int) $merchant->getKey(), 404);

        $this->library->assertUsableForOwner($template, BannerPosition::SCOPE_MERCHANT);
        $this->assertPositionCompatible($data['position'] ?? $template->default_position, BannerPosition::SCOPE_MERCHANT);

        return DB::transaction(function () use ($template, $merchant, $shop, $data, $actor): Banner {
            if ($this->limits->usedSlots($merchant, $shop, true) >= $this->limits->limitPerShop()) {
                throw ValidationException::withMessages([
                    'banner_template_uuid' => 'This shop has reached its maximum of '.$this->limits->limitPerShop().' banner slots. Edit or replace one of the existing banners.',
                ]);
            }

            return Banner::query()->create([
                ...$this->templatePayload($template, $data),
                'merchant_id' => $merchant->getKey(),
                'shop_id' => $shop->getKey(),
                'created_by' => $actor->getKey(),
                'updated_by' => $actor->getKey(),
            ]);
        });
    }

    public function replaceBannerTemplate(Banner $banner, BannerTemplate $template, array $data, User $actor): Banner
    {
        $scope = $banner->merchant_id ? BannerPosition::SCOPE_MERCHANT : BannerPosition::SCOPE_ADMIN;
        $mode = (string) ($data['apply_template_defaults'] ?? self::RESET_IMAGES_ONLY);

        $this->library->assertUsableForOwner($template, $scope);

        if ($mode === self::RESET_ALL) {
            $this->assertPositionCompatible($data['position'] ?? $template->default_position, $scope);
        }

        $oldDesktop = $banner->desktop_image_path;
        $oldMobile = $banner->mobile_image_path;
        $deleteOldCustom = $banner->usesCustomUpload();

        DB::transaction(function () use ($banner, $template, $data, $actor, $mode): void {
            $payload = [
                'banner_template_id' => $template->getKey(),
                'source_type' => BannerSourceType::TEMPLATE->value,
                'desktop_image_path' => $template->desktop_image_path,
                'mobile_image_path' => $template->mobile_image_path,
                'updated_by' => $actor->getKey(),
            ];

            if (in_array($mode, [self::RESET_TEXT, self::RESET_ALL], true)) {
                $payload = [
                    ...$payload,
                    'title' => $this->valueOrDefault($data['title'] ?? null, $template->default_title),
                    'subtitle' => $this->valueOrDefault($data['subtitle'] ?? null, $template->default_subtitle),
                    'button_text' => $this->valueOrDefault($data['button_text'] ?? null, $template->default_button_text),
                    'description' => $this->valueOrDefault($data['description'] ?? null, $template->description),
                ];
            }

            if ($mode === self::RESET_ALL) {
                $payload = [
                    ...$payload,
                    'position' => $data['position'] ?? $template->default_position,
                    'sort_order' => (int) ($data['sort_order'] ?? $template->sort_order ?? 0),
                ];
            }

            $banner->forceFill($payload)->save();
        });

        if ($deleteOldCustom) {
            $this->images->deleteOwnedCustom($oldDesktop, $banner);
            $this->images->deleteOwnedCustom($oldMobile, $banner);
        }

        return $banner->refresh();
    }

    /**
     * @return array{starts_at: ?CarbonImmutable, ends_at: ?CarbonImmutable}
     */
    public function recommendedSchedule(BannerTemplate $template): array
    {
        $eventDate = $this->fixedEventDate((string) $template->event_code);

        if ($eventDate === null) {
            return ['starts_at' => null, 'ends_at' => null];
        }

        return [
            'starts_at' => $eventDate->addDays((int) ($template->start_offset_days ?? 0))->startOfDay(),
            'ends_at' => $eventDate->addDays((int) ($template->end_offset_days ?? 0))->endOfDay(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function templatePayload(BannerTemplate $template, array $data): array
    {
        $linkType = $data['link_type'] ?? BannerLinkType::NONE->value;

        return [
            'banner_template_id' => $template->getKey(),
            'source_type' => BannerSourceType::TEMPLATE->value,
            'position' => $data['position'] ?? $template->default_position,
            'title' => $this->valueOrDefault($data['title'] ?? null, $template->default_title),
            'subtitle' => $this->valueOrDefault($data['subtitle'] ?? null, $template->default_subtitle),
            'button_text' => $this->valueOrDefault($data['button_text'] ?? null, $template->default_button_text),
            'description' => $this->valueOrDefault($data['description'] ?? null, $template->description),
            'desktop_image_path' => $template->desktop_image_path,
            'mobile_image_path' => $template->mobile_image_path,
            'link_type' => $linkType,
            'link_value' => $linkType === BannerLinkType::NONE->value ? null : $this->nullableString($data['link_value'] ?? null),
            'open_in_new_tab' => $linkType === BannerLinkType::CUSTOM_URL->value && (bool) ($data['open_in_new_tab'] ?? false),
            'sort_order' => (int) ($data['sort_order'] ?? $template->sort_order ?? 0),
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'status' => $data['status'] ?? Banner::STATUS_INACTIVE,
        ];
    }

    private function assertPositionCompatible(mixed $value, string $scope): void
    {
        $position = BannerPosition::tryFrom((string) $value);

        if ($position === null || $position->scope() !== $scope) {
            throw ValidationException::withMessages([
                'position' => 'This template default position is not available for the selected owner. Choose a valid position.',
            ]);
        }
    }

    private function valueOrDefault(mixed $value, mixed $default): ?string
    {
        $value = $this->nullableString($value);

        return $value ?? $this->nullableString($default);
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    private function fixedEventDate(string $eventCode): ?CarbonImmutable
    {
        if ($eventCode === '') {
            return null;
        }

        $timezone = app(BusinessTimeService::class)->timezoneName();
        $year = (int) now($timezone)->format('Y');

        return match ($eventCode) {
            'new_year' => CarbonImmutable::create($year, 1, 1, 0, 0, 0, $timezone),
            'republic_day' => CarbonImmutable::create($year, 1, 26, 0, 0, 0, $timezone),
            'valentines_day' => CarbonImmutable::create($year, 2, 14, 0, 0, 0, $timezone),
            'independence_day' => CarbonImmutable::create($year, 8, 15, 0, 0, 0, $timezone),
            'christmas' => CarbonImmutable::create($year, 12, 25, 0, 0, 0, $timezone),
            default => null,
        };
    }
}
