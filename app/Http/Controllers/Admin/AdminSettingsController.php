<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminSetting;
use App\Models\SystemSetting;
use App\Models\SystemSettingGroup;
use App\Services\Admin\AdminSettingsInitializer;
use App\Services\Admin\AdminSettingsService;
use App\Services\Marketplace\MarketplaceLogoService;
use App\Support\CurrencyCatalog;
use App\Support\TimezoneCatalog;
use Database\Seeders\MasterData\StorefrontBannerSettingSeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminSettingsController extends Controller
{
    public function __construct(
        private readonly AdminSettingsInitializer $initializer,
        private readonly AdminSettingsService $settings,
        private readonly TimezoneCatalog $timezones,
        private readonly CurrencyCatalog $currencies,
        private readonly MarketplaceLogoService $marketplaceLogo,
    ) {}

    public function edit(): View
    {
        $this->initializer->initialize();
        app(StorefrontBannerSettingSeeder::class)->run();
        $marketplaceLogoSetting = $this->ensureMarketplaceLogoSetting();

        return view('admin.settings.edit', [
            'defaults' => $this->initializer->defaults(),
            'settings' => $this->settings->all(),
            'storefrontBannerMaxPerShop' => SystemSetting::query()
                ->where('key', 'storefront_banner.max_per_shop')
                ->value('value') ?? '3',
            'marketplaceLogoPath' => $marketplaceLogoSetting->value ?: MarketplaceLogoService::DEFAULT_LOGO_PATH,
            'marketplaceLogoUrl' => $this->marketplaceLogo->url(),
            'timezones' => $this->timezones->all(),
            'currencies' => $this->currencies->all(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'marketplace_logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'remove_marketplace_logo' => ['nullable', 'boolean'],
        ]);

        $payload = (array) $request->input('settings', []);

        foreach ($this->initializer->defaults() as $group => $definitions) {
            foreach ($definitions as $key => $definition) {
                $rawValue = $payload[$group][$key] ?? null;
                $value = $this->normalizeInputValue($rawValue, $definition['type']);

                try {
                    $this->settings->setTyped($group, $key, $value, $definition['type']);
                } catch (\InvalidArgumentException $exception) {
                    throw ValidationException::withMessages([
                        "settings.{$group}.{$key}" => $exception->getMessage(),
                    ]);
                }
            }
        }

        $this->updateStorefrontBannerSettings($payload);
        $this->updateMarketplaceLogo($request);

        return back()->with('success', 'Admin settings updated successfully.');
    }

    private function updateStorefrontBannerSettings(array $payload): void
    {
        app(StorefrontBannerSettingSeeder::class)->run();

        $value = $payload['storefront_banner']['max_per_shop'] ?? null;

        if (filter_var($value, FILTER_VALIDATE_INT) === false || (int) $value < 1 || (int) $value > 20) {
            throw ValidationException::withMessages([
                'settings.storefront_banner.max_per_shop' => 'Maximum banners per shop must be between 1 and 20.',
            ]);
        }

        SystemSetting::query()
            ->where('key', 'storefront_banner.max_per_shop')
            ->update([
                'value' => (string) (int) $value,
                'updated_by' => Auth::id(),
                'updated_at' => now(),
            ]);
    }

    private function updateMarketplaceLogo(Request $request): void
    {
        $setting = $this->ensureMarketplaceLogoSetting();
        $previousPath = $setting->value;

        if ($request->hasFile('marketplace_logo')) {
            $file = $request->file('marketplace_logo');
            $path = $file->storeAs(
                MarketplaceLogoService::MANAGED_DIRECTORY,
                'marketplace-logo-'.Str::lower(Str::random(5)).'.'.$file->extension(),
                'public',
            );

            $setting->forceFill([
                'value' => $path,
                'updated_by' => Auth::id(),
                'updated_at' => now(),
            ])->save();

            $this->marketplaceLogo->deleteManaged($previousPath);

            return;
        }

        if ($request->boolean('remove_marketplace_logo')) {
            $setting->forceFill([
                'value' => MarketplaceLogoService::DEFAULT_LOGO_PATH,
                'updated_by' => Auth::id(),
                'updated_at' => now(),
            ])->save();

            $this->marketplaceLogo->deleteManaged($previousPath);
        }
    }

    private function ensureMarketplaceLogoSetting(): SystemSetting
    {
        $group = SystemSettingGroup::query()->updateOrCreate(
            ['slug' => 'marketplace'],
            [
                'name' => 'Marketplace',
                'sort_order' => 15,
                'status' => 'active',
                'deleted_at' => null,
            ],
        );

        $setting = SystemSetting::query()->firstOrNew(['key' => MarketplaceLogoService::SETTING_KEY]);

        $setting->forceFill([
            'group_id' => $group->getKey(),
            'label' => 'Marketplace Logo',
            'value' => $setting->exists ? $setting->value : MarketplaceLogoService::DEFAULT_LOGO_PATH,
            'value_type' => SystemSetting::TYPE_STRING,
            'is_public' => false,
            'is_encrypted' => false,
            'sort_order' => 10,
            'status' => SystemSetting::STATUS_ACTIVE,
            'deleted_at' => null,
        ])->save();

        return $setting;
    }

    private function normalizeInputValue(mixed $value, string $type): mixed
    {
        return match ($type) {
            AdminSetting::TYPE_BOOLEAN => (bool) $value,
            AdminSetting::TYPE_INTEGER => (int) $value,
            AdminSetting::TYPE_DECIMAL => (float) $value,
            AdminSetting::TYPE_JSON => is_string($value) ? json_decode($value ?: 'null', true, 512, JSON_THROW_ON_ERROR) : $value,
            default => $value,
        };
    }
}
