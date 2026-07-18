<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use App\Models\MerchantProfile;
use App\Models\MerchantSetting;
use App\Services\Merchant\MerchantSettingsInitializer;
use App\Services\Merchant\MerchantSettingsService;
use App\Services\Merchant\MerchantShopContextService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MerchantSettingsController extends Controller
{
    public function __construct(
        private readonly MerchantShopContextService $shopContextService,
        private readonly MerchantSettingsInitializer $initializer,
        private readonly MerchantSettingsService $settings,
    ) {
    }

    public function edit(Request $request): View
    {
        $merchant = $this->activeMerchant($request);
        $this->initializer->initialize((int) $merchant->getKey());

        return view('merchant.settings.edit', [
            'defaults' => $this->initializer->defaults(),
            'settings' => $this->settings->all((int) $merchant->getKey()),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $merchant = $this->activeMerchant($request);
        $payload = (array) $request->input('settings', []);

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

        return back()->with('success', 'Merchant settings updated successfully.');
    }

    private function activeMerchant(Request $request): MerchantProfile
    {
        $merchant = $this->shopContextService->activeMerchantForUser($request->user());
        abort_unless($merchant instanceof MerchantProfile, 403);

        return $merchant;
    }

    private function normalizeInputValue(mixed $value, string $type): mixed
    {
        return match ($type) {
            MerchantSetting::TYPE_BOOLEAN => (bool) $value,
            MerchantSetting::TYPE_INTEGER => (int) $value,
            MerchantSetting::TYPE_DECIMAL => (float) $value,
            MerchantSetting::TYPE_JSON => is_string($value) ? json_decode($value ?: 'null', true, 512, JSON_THROW_ON_ERROR) : $value,
            default => $value,
        };
    }
}
