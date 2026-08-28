<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use App\Models\MerchantProfile;
use App\Models\MerchantSetting;
use App\Models\Shop;
use App\Models\ShopSetting;
use App\Services\Merchant\MerchantSettingsInitializer;
use App\Services\Merchant\MerchantSettingsService;
use App\Services\Merchant\MerchantShopContextService;
use App\Services\Merchant\ShopSettingsInitializer;
use App\Services\Merchant\ShopSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MerchantSettingsController extends Controller
{
    public function __construct(
        private readonly MerchantShopContextService $shopContextService,
        private readonly MerchantSettingsInitializer $initializer,
        private readonly MerchantSettingsService $settings,
        private readonly ShopSettingsInitializer $shopSettingsInitializer,
        private readonly ShopSettingsService $shopSettings,
    ) {
    }

    public function edit(Request $request): View
    {
        $merchant = $this->activeMerchant($request);
        $activeShop = $this->activeShop($request, $merchant);

        $this->initializer->initialize((int) $merchant->getKey());
        if ($activeShop instanceof Shop) {
            $this->shopSettingsInitializer->initialize((int) $activeShop->getKey());
        }

        return view('merchant.settings.edit', [
            'defaults' => $this->initializer->defaults(),
            'settings' => $this->settings->all((int) $merchant->getKey()),
            'activeShop' => $activeShop,
            'activeShopLabel' => $this->shopContextService->label($activeShop),
            'shopSettingsDefaults' => $this->shopSettingsInitializer->defaults(),
            'shopSettings' => $activeShop instanceof Shop
                ? $this->shopSettings->all((int) $activeShop->getKey())
                : collect(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $merchant = $this->activeMerchant($request);
        $activeShop = $this->activeShop($request, $merchant);
        $payload = (array) $request->input('settings', []);
        $shopPayload = (array) $request->input('shop_settings', []);

        if ($activeShop instanceof Shop) {
            $this->validateShopSettings($request, $activeShop);
        }

        foreach ($this->initializer->defaults() as $group => $definitions) {
            foreach ($definitions as $key => $definition) {
                $rawValue = $payload[$group][$key] ?? null;
                $value = $this->normalizeInputValue($rawValue, $definition['type']);

                try {
                    $this->settings->setTyped(
                        (int) $merchant->getKey(),
                        $group,
                        $key,
                        $value,
                        $definition['type'],
                    );
                } catch (\InvalidArgumentException $exception) {
                    throw ValidationException::withMessages([
                        "settings.{$group}.{$key}" => $exception->getMessage(),
                    ]);
                }
            }
        }

        if ($activeShop instanceof Shop) {
            $this->saveShopSettings($request, $activeShop, $shopPayload);
        }

        return back()
            ->with('success', 'Merchant settings updated successfully.')
            ->with('active_settings_tab', $this->activeSettingsTab($request->input('active_tab')));
    }

    private function activeMerchant(Request $request): MerchantProfile
    {
        $merchant = $this->shopContextService->activeMerchantForUser($request->user());
        abort_unless($merchant instanceof MerchantProfile, 403);

        return $merchant;
    }

    private function activeShop(Request $request, MerchantProfile $merchant): ?Shop
    {
        $shops = $this->shopContextService->activeShops($merchant);

        return $this->shopContextService->resolveActiveShop($shops, $request->session()->get('active_shop_id'));
    }

    private function activeSettingsTab(mixed $tab): string
    {
        $allowedTabs = [
            'general',
            'pos',
            'orders',
            'inventory',
            'products',
            'payments',
            'delivery',
            'receipts',
            'notifications',
            'advanced',
        ];

        return in_array($tab, $allowedTabs, true) ? $tab : 'pos';
    }

    /**
     * @param array<string, mixed> $shopPayload
     */
    private function saveShopSettings(Request $request, Shop $shop, array $shopPayload): void
    {
        $shopId = (int) $shop->getKey();
        $existingQrPath = $this->shopSettings->get($shopId, 'payment', 'merchant_upi_qr_path');
        $newQrPath = null;

        if ($request->hasFile('merchant_upi_qr')) {
            $newQrPath = $request->file('merchant_upi_qr')->store(
                'shops/'.$shopId.'/settings/upi-qr',
                'public',
            );
        }

        foreach ($this->shopSettingsInitializer->defaults() as $group => $definitions) {
            foreach ($definitions as $key => $definition) {
                if ($group === 'payment' && $key === 'merchant_upi_qr_path') {
                    $rawValue = $newQrPath ?: $existingQrPath;
                } elseif ($group === 'payment' && $key === 'online_payment_enabled') {
                    $rawValue = false;
                } else {
                    $rawValue = $shopPayload[$group][$key] ?? null;
                }

                $value = $this->normalizeInputValue($rawValue, $definition['type'], true);
                $value = $this->normalizeShopSettingValue($group, $key, $value, $shopPayload);

                try {
                    $this->shopSettings->setTyped($shopId, $group, $key, $value, $definition['type']);
                } catch (\InvalidArgumentException $exception) {
                    throw ValidationException::withMessages([
                        "shop_settings.{$group}.{$key}" => $exception->getMessage(),
                    ]);
                }
            }
        }

        if ($newQrPath && is_string($existingQrPath) && $existingQrPath !== $newQrPath) {
            Storage::disk('public')->delete($existingQrPath);
        }
    }

    private function validateShopSettings(Request $request, Shop $shop): void
    {
        $validated = $request->validate([
            'shop_settings.fulfillment.delivery_enabled' => ['nullable', 'boolean'],
            'shop_settings.fulfillment.delivery_scope' => ['nullable', Rule::in(['local_only', 'nationwide'])],
            'shop_settings.fulfillment.delivery_min_order_amount' => ['nullable', 'numeric', 'gte:0'],
            'shop_settings.fulfillment.delivery_flat_charge' => ['nullable', 'numeric', 'gte:0'],
            'shop_settings.fulfillment.free_delivery_above' => ['nullable', 'numeric', 'gte:0'],
            'shop_settings.fulfillment.delivery_estimate_min_days' => ['nullable', 'integer', 'gte:0'],
            'shop_settings.fulfillment.delivery_estimate_max_days' => ['nullable', 'integer', 'gte:0'],
            'shop_settings.fulfillment.pickup_enabled' => ['nullable', 'boolean'],
            'shop_settings.fulfillment.pickup_instructions' => ['nullable', 'string', 'max:1000'],
            'shop_settings.payment.cod_enabled' => ['nullable', 'boolean'],
            'shop_settings.payment.cod_min_order_amount' => ['nullable', 'numeric', 'gte:0'],
            'shop_settings.payment.cod_max_order_amount' => ['nullable', 'numeric', 'gte:0'],
            'shop_settings.payment.cash_at_shop_enabled' => ['nullable', 'boolean'],
            'shop_settings.payment.merchant_upi_enabled' => ['nullable', 'boolean'],
            'shop_settings.payment.merchant_upi_id' => ['nullable', 'string', 'max:191'],
            'shop_settings.payment.merchant_upi_payee_name' => ['nullable', 'string', 'max:191'],
            'shop_settings.returns.refund_allowed' => ['nullable', 'boolean'],
            'shop_settings.returns.refund_window_days' => ['nullable', 'integer', 'gte:0'],
            'shop_settings.returns.exchange_allowed' => ['nullable', 'boolean'],
            'shop_settings.returns.exchange_window_days' => ['nullable', 'integer', 'gte:0'],
            'merchant_upi_qr' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $min = data_get($validated, 'shop_settings.payment.cod_min_order_amount');
        $max = data_get($validated, 'shop_settings.payment.cod_max_order_amount');

        if (
            $min !== null
            && $max !== null
            && (float) $min > 0
            && (float) $max > 0
            && (float) $min > (float) $max
        ) {
            throw ValidationException::withMessages([
                'shop_settings.payment.cod_min_order_amount' => 'The minimum order amount must not exceed the maximum order amount.',
            ]);
        }

        $estimateMin = data_get($validated, 'shop_settings.fulfillment.delivery_estimate_min_days');
        $estimateMax = data_get($validated, 'shop_settings.fulfillment.delivery_estimate_max_days');

        if ($estimateMin !== null && $estimateMax !== null && (int) $estimateMax < (int) $estimateMin) {
            throw ValidationException::withMessages([
                'shop_settings.fulfillment.delivery_estimate_max_days' => 'The maximum delivery estimate must be greater than or equal to the minimum estimate.',
            ]);
        }

        $upiEnabled = (bool) data_get($validated, 'shop_settings.payment.merchant_upi_enabled', false);
        if (! $upiEnabled) {
            return;
        }

        $existingQrPath = $this->shopSettings->get((int) $shop->getKey(), 'payment', 'merchant_upi_qr_path');
        $messages = [];

        if (blank(data_get($validated, 'shop_settings.payment.merchant_upi_id'))) {
            $messages['shop_settings.payment.merchant_upi_id'] = 'The UPI ID is required when Direct Merchant UPI is enabled.';
        }

        if (blank(data_get($validated, 'shop_settings.payment.merchant_upi_payee_name'))) {
            $messages['shop_settings.payment.merchant_upi_payee_name'] = 'The payee name is required when Direct Merchant UPI is enabled.';
        }

        if (! $request->hasFile('merchant_upi_qr') && blank($existingQrPath)) {
            $messages['merchant_upi_qr'] = 'The UPI QR code is required when Direct Merchant UPI is enabled.';
        }

        if ($messages !== []) {
            throw ValidationException::withMessages($messages);
        }
    }

    /**
     * @param array<string, mixed> $shopPayload
     */
    private function normalizeShopSettingValue(string $group, string $key, mixed $value, array $shopPayload): mixed
    {
        if ($group === 'returns') {
            if ($key === 'refund_window_days') {
                return (bool) data_get($shopPayload, 'returns.refund_allowed', false) ? ($value ?? 0) : 0;
            }

            if ($key === 'exchange_window_days') {
                return (bool) data_get($shopPayload, 'returns.exchange_allowed', false) ? ($value ?? 0) : 0;
            }
        }

        if ($group !== 'fulfillment') {
            return $value;
        }

        if ($key === 'delivery_scope') {
            return $value ?: 'local_only';
        }

        if (in_array($key, ['delivery_min_order_amount', 'free_delivery_above'], true)) {
            return $value === null || (float) $value <= 0 ? null : $value;
        }

        if ($key === 'delivery_flat_charge') {
            return $value === null ? 0 : $value;
        }

        return $value;
    }

    private function normalizeInputValue(mixed $value, string $type, bool $emptyNumericAsNull = false): mixed
    {
        if ($emptyNumericAsNull && $value === '') {
            return match ($type) {
                MerchantSetting::TYPE_INTEGER, MerchantSetting::TYPE_DECIMAL,
                ShopSetting::TYPE_INTEGER, ShopSetting::TYPE_DECIMAL => null,
                default => $value,
            };
        }

        return match ($type) {
            MerchantSetting::TYPE_BOOLEAN, ShopSetting::TYPE_BOOLEAN => (bool) $value,
            MerchantSetting::TYPE_INTEGER, ShopSetting::TYPE_INTEGER => (int) $value,
            MerchantSetting::TYPE_DECIMAL, ShopSetting::TYPE_DECIMAL => (float) $value,
            MerchantSetting::TYPE_JSON, ShopSetting::TYPE_JSON => is_string($value) ? json_decode($value ?: 'null', true, 512, JSON_THROW_ON_ERROR) : $value,
            default => $value,
        };
    }
}
