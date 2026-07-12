<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Merchant\MerchantShopContextService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class EnsureMerchantActiveShop
{
    public function __construct(
        private readonly MerchantShopContextService $shopContextService,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return redirect()->route('merchant.login');
        }

        $merchant = $this->shopContextService->activeMerchantForUser($user);

        abort_unless($merchant !== null, 403);

        $activeShops = $this->shopContextService->activeShops($merchant);
        $activeShop = $this->shopContextService->resolveActiveShop(
            $activeShops,
            $request->session()->get('active_shop_id'),
        );

        $request->session()->put('merchant_id', $merchant->getKey());
        $request->session()->put('active_role_id', $this->shopContextService->merchantRoleId());

        if ($activeShop !== null) {
            $request->session()->put('active_shop_id', $activeShop->getKey());
            $request->session()->put('active_shop_name', $this->shopContextService->label($activeShop));
        } else {
            $request->session()->forget('active_shop_id');
            $request->session()->forget('active_shop_name');
        }

        View::share('merchantActiveShopContext', [
            'merchant' => $merchant,
            'shops' => $activeShops,
            'activeShop' => $activeShop,
            'activeShopLabel' => $this->shopContextService->label($activeShop),
        ]);

        return $next($request);
    }
}
