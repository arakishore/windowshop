<?php

namespace App\Http\Controllers\Merchant\Auth;

use App\Http\Controllers\Controller;
use App\Services\Merchant\MerchantDashboardService;
use App\Services\Merchant\MerchantAuthenticationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MerchantAuthController extends Controller
{
    public function __construct(
        private readonly MerchantAuthenticationService $merchantAuthenticationService,
        private readonly MerchantDashboardService $merchantDashboardService,
    ) {
    }

    public function showLoginForm(): View
    {
        return view('merchant.auth.login');
    }

    /**
     * @throws ValidationException
     */
    public function authenticate(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
        ]);

        $context = $this->merchantAuthenticationService->authenticateWeb(
            $request,
            $credentials,
            $request->boolean('remember'),
        );

        $request->session()->regenerate();
        $request->session()->put('merchant_id', $context['merchant']->getKey());
        $request->session()->forget('active_shop_id');
        $request->session()->forget('active_shop_name');

        $activeShops = $this->merchantAuthenticationService->activeShopsForMerchant($context['merchant']);

        if ($activeShops->count() === 1) {
            $activeShop = $activeShops->first();

            $request->session()->put('active_shop_id', $activeShop->getKey());
            $request->session()->put('active_shop_name', $activeShop->name.($activeShop->city?->name ? ' - '.$activeShop->city->name : ''));
        }

        $this->merchantAuthenticationService->recordSuccessfulWebLogin(
            $request,
            $context['user'],
        );

        return redirect()->intended(route('merchant.dashboard'));
    }

    public function dashboard(Request $request): View
    {
        $merchant = $this->merchantAuthenticationService->activeMerchantForUser($request->user());

        abort_unless($merchant !== null, 403);

        return view('merchant.dashboard', [
            'dashboard' => $this->merchantDashboardService->data(
                $merchant,
                (int) $request->session()->get('active_shop_id') ?: null,
            ),
        ]);
    }

    public function logout(Request $request): RedirectResponse
    {
        $this->merchantAuthenticationService->logoutWeb($request);

        return redirect()
            ->route('merchant.login')
            ->with('success', 'You have been logged out successfully.');
    }
}
