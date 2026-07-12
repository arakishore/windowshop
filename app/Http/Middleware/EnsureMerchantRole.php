<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Merchant\MerchantAuthenticationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMerchantRole
{
    public function __construct(
        private readonly MerchantAuthenticationService $merchantAuthenticationService,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return redirect()->route('merchant.login');
        }

        $merchant = $this->merchantAuthenticationService->activeMerchantForUser($user);

        abort_unless($merchant !== null, 403);

        $request->session()->put('merchant_id', $merchant->getKey());

        return $next($request);
    }
}
