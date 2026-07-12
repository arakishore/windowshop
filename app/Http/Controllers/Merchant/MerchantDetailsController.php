<?php

namespace App\Http\Controllers\Merchant;

use App\Enums\MerchantBusinessType;
use App\Enums\MerchantVerificationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Merchant\UpdateMerchantDetailsRequest;
use App\Services\Merchant\MerchantAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MerchantDetailsController extends Controller
{
    public function __construct(
        private readonly MerchantAccountService $merchantAccountService,
    ) {
    }

    public function edit(Request $request): View
    {
        $merchant = $this->merchantAccountService->merchantForUser($request->user());

        return view('merchant.details.edit', [
            'merchant' => $merchant,
            'businessTypes' => MerchantBusinessType::options(),
            'lockedAfterVerification' => $merchant->verification_status === MerchantVerificationStatus::APPROVED->value,
        ]);
    }

    public function update(UpdateMerchantDetailsRequest $request): RedirectResponse
    {
        $merchant = $this->merchantAccountService->merchantForUser($request->user());

        $this->merchantAccountService->updateDetails(
            $request->user(),
            $merchant,
            $request->validated(),
        );

        return back()->with('success', 'Merchant details updated successfully.');
    }
}
