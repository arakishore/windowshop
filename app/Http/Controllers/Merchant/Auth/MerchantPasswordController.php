<?php

namespace App\Http\Controllers\Merchant\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Merchant\UpdateMerchantPasswordRequest;
use App\Services\Merchant\MerchantAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MerchantPasswordController extends Controller
{
    public function __construct(
        private readonly MerchantAccountService $merchantAccountService,
    ) {
    }

    public function edit(): View
    {
        return view('merchant.password.edit');
    }

    public function update(UpdateMerchantPasswordRequest $request): RedirectResponse
    {
        $this->merchantAccountService->updatePassword($request->user(), $request->validated());

        $request->session()->regenerate();

        return back()->with('success', 'Password changed successfully.');
    }
}
