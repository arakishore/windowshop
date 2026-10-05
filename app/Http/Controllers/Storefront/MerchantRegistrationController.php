<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\RegisterMerchantRequest;
use App\Models\MerchantProfile;
use App\Models\Testimonial;
use App\Models\User;
use App\Services\Merchant\MerchantService;
use App\Services\Storefront\NavigationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MerchantRegistrationController extends Controller
{
    public function __construct(
        private readonly MerchantService $merchants,
        private readonly NavigationService $navigation,
    ) {}

    public function create(): View|RedirectResponse
    {
        $user = request()->user();

        if ($user instanceof User && MerchantProfile::withTrashed()->where('user_id', $user->getKey())->exists()) {
            return redirect()->route('merchant.dashboard')
                ->with('info', 'Your merchant account already exists.');
        }

        return view('storefront.pages.merchant-register', [
            'businessTypes' => $this->merchants->businessTypes(),
            'storefrontNavigationCategories' => $this->navigation->getMarketplaceCategories(),
            'existingUser' => $user instanceof User ? $user : null,
            'merchantTestimonials' => Testimonial::query()
                ->ofType(Testimonial::TYPE_MERCHANT)
                ->active()
                ->ordered()
                ->limit(4)
                ->get(),
        ]);
    }

    public function store(RegisterMerchantRequest $request): RedirectResponse
    {
        $user = $request->user();
        $email = strtolower((string) $request->validated('email'));

        if (! $user instanceof User && User::query()->where('email', $email)->exists()) {
            $request->session()->put('url.intended', route('storefront.merchant-register'));

            throw ValidationException::withMessages([
                'email' => 'An account already exists with this email. Sign in to continue merchant registration.',
            ]);
        }

        if ($user instanceof User && MerchantProfile::withTrashed()->where('user_id', $user->getKey())->exists()) {
            return redirect()->route('merchant.dashboard')
                ->with('info', 'Your merchant account already exists.');
        }

        $merchant = $this->merchants->registerStorefront($request->validated(), $user);

        if (! $user instanceof User) {
            Auth::login($merchant->user);
            $request->session()->regenerate();
        }

        $request->session()->put('merchant_id', $merchant->getKey());
        $request->session()->put('active_role_id', $this->merchants->merchantRoleId());

        return redirect()->route('storefront.merchant-register.success');
    }

    public function success(): View
    {
        return view('storefront.pages.merchant-register-success', [
            'storefrontNavigationCategories' => $this->navigation->getMarketplaceCategories(),
        ]);
    }
}
