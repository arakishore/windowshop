<?php

namespace App\Http\Controllers\Merchant\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Merchant\UpdateMerchantProfileRequest;
use App\Services\Merchant\MerchantAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class MerchantProfileController extends Controller
{
    public function __construct(
        private readonly MerchantAccountService $merchantAccountService,
    ) {
    }

    public function edit(): View
    {
        $user = auth()->user();
        $merchant = $this->merchantAccountService->merchantForUser($user);

        return view('merchant.profile.edit', [
            'user' => $user,
            'merchant' => $merchant,
        ]);
    }

    public function update(UpdateMerchantProfileRequest $request): RedirectResponse
    {
        $user = $request->user();
        $merchant = $this->merchantAccountService->merchantForUser($user);

        $this->merchantAccountService->updateProfile($user, $merchant, $request->validated());

        return back()->with('success', 'Profile updated successfully.');
    }
}
