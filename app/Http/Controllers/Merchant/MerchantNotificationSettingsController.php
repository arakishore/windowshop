<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use App\Models\MerchantProfile;
use App\Models\Shop;
use App\Services\Merchant\MerchantShopContextService;
use App\Services\Notification\MerchantOperationalEmailRecipientResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MerchantNotificationSettingsController extends Controller
{
    public function __construct(
        private readonly MerchantShopContextService $shopContext,
        private readonly MerchantOperationalEmailRecipientResolver $recipients,
    ) {}

    public function edit(Request $request): View
    {
        $shop = $this->activeShop($request);

        return view('merchant.notification-settings.edit', [
            'shop' => $shop,
            'recipients' => $this->recipients->resolve($shop),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $shop = $this->activeShop($request);
        $email = collect(['additional_to', 'cc', 'bcc'])->mapWithKeys(fn (string $group): array => [
            $group => collect(explode(',', (string) $request->input("email.{$group}", '')))
                ->map(fn (string $value): string => mb_strtolower(trim($value)))
                ->filter()
                ->values()
                ->all(),
        ])->all();
        $data = Validator::make(['email' => $email], [
            'email.additional_to' => ['array', 'max:10'],
            'email.additional_to.*' => ['email:rfc', 'max:255'],
            'email.cc' => ['array', 'max:10'],
            'email.cc.*' => ['email:rfc', 'max:255'],
            'email.bcc' => ['array', 'max:10'],
            'email.bcc.*' => ['email:rfc', 'max:255'],
        ], [
            'email.additional_to.max' => 'Additional To may contain no more than 10 email addresses.',
            'email.cc.max' => 'CC may contain no more than 10 email addresses.',
            'email.bcc.max' => 'BCC may contain no more than 10 email addresses.',
            'email.additional_to.*.email' => 'One or more Additional To email addresses are invalid.',
            'email.cc.*.email' => 'One or more CC email addresses are invalid.',
            'email.bcc.*.email' => 'One or more BCC email addresses are invalid.',
        ])->validate();

        $groups = [
            'additional_to' => data_get($data, 'email.additional_to', []),
            'cc' => data_get($data, 'email.cc', []),
            'bcc' => data_get($data, 'email.bcc', []),
        ];
        $primary = $this->recipients->resolve($shop)['primary'];
        $seen = array_filter([$primary]);
        foreach ($groups as $group => $addresses) {
            foreach ($addresses as $index => $address) {
                if (in_array($address, $seen, true)) {
                    throw ValidationException::withMessages([
                        "email.{$group}.{$index}" => $address === $primary
                            ? 'The shop owner email is already the required primary recipient.'
                            : 'This email address is already used in another recipient group.',
                    ]);
                }
                $seen[] = $address;
            }
        }

        $this->recipients->save($shop, $groups);

        return back()->with('success', 'Notification recipients updated successfully.');
    }

    private function activeShop(Request $request): Shop
    {
        $merchant = $this->shopContext->activeMerchantForUser($request->user());
        abort_unless($merchant instanceof MerchantProfile, 403);
        $shop = $this->shopContext->resolveActiveShop($this->shopContext->activeShops($merchant), $request->session()->get('active_shop_id'));
        abort_unless($shop instanceof Shop, 403);

        return $shop;
    }
}
