<?php

namespace App\Http\Controllers\Customer\Auth;

use App\Enums\UserRegistrationSource;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Cart\CartMergeService;
use App\Services\Cart\CartResolver;
use App\Services\Checkout\CheckoutFlowService;
use App\Services\Storefront\StorefrontCustomerContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CustomerAuthController extends Controller
{
    public function __construct(
        private readonly CartMergeService $cartMerge,
        private readonly CheckoutFlowService $checkout,
    ) {
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $guestToken = $request->session()->get(CartResolver::SESSION_TOKEN_KEY);
        $user = User::query()
            ->where('email', strtolower((string) $credentials['email']))
            ->first();

        if (! $user instanceof User
            || $user->status !== 'active'
            || $user->deleted_at !== null
            || ! Hash::check((string) $credentials['password'], (string) $user->password)
            || ! $this->userHasCustomerRole($user)) {
            throw ValidationException::withMessages([
                'email' => 'The provided credentials do not match our records.',
            ]);
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();
        $this->activateCustomerRole($request, $user);
        $this->cartMerge->mergeGuestTokenIntoCustomer(is_string($guestToken) ? $guestToken : null, $user);
        $request->session()->forget(CartResolver::SESSION_TOKEN_KEY);
        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        if ($this->checkout->hasIntent($request) && ! $this->checkout->hasCartItems($request)) {
            return redirect()
                ->route('storefront.cart')
                ->with('error', 'Your cart is empty.');
        }

        return redirect($this->redirectAfterAuth($request));
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'mobile' => ['nullable', 'string', 'max:20', 'unique:users,mobile'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'terms' => ['accepted'],
        ]);

        $guestToken = $request->session()->get(CartResolver::SESSION_TOKEN_KEY);
        $user = DB::transaction(function () use ($data): User {
            $name = trim((string) $data['name'].' '.($data['last_name'] ?? ''));
            $user = User::query()->create([
                'name' => $name,
                'email' => strtolower((string) $data['email']),
                'password' => Hash::make((string) $data['password']),
                'registration_source' => UserRegistrationSource::WEB->value,
            ]);
            $user->forceFill([
                'mobile' => $data['mobile'] ?? null,
                'status' => 'active',
            ])->save();
            $this->assignCustomerRole($user);

            return $user->refresh();
        });

        Auth::login($user);
        $request->session()->regenerate();
        $this->activateCustomerRole($request, $user);
        $this->cartMerge->mergeGuestTokenIntoCustomer(is_string($guestToken) ? $guestToken : null, $user);
        $request->session()->forget(CartResolver::SESSION_TOKEN_KEY);

        if ($this->checkout->hasIntent($request) && ! $this->checkout->hasCartItems($request)) {
            return redirect()
                ->route('storefront.cart')
                ->with('error', 'Your cart is empty.');
        }

        return redirect($this->redirectAfterAuth($request));
    }

    private function redirectAfterAuth(Request $request): string
    {
        return $this->checkout->hasIntent($request)
            ? route(CheckoutFlowService::ADDRESS_ROUTE)
            : route('storefront.home');
    }

    private function activateCustomerRole(Request $request, User $user): void
    {
        $request->session()->put(StorefrontCustomerContext::ACTIVE_ROLE_SESSION_KEY, $this->customerRoleId());
    }

    private function assignCustomerRole(User $user): void
    {
        DB::table('auth_user_roles')->updateOrInsert([
            'user_id' => $user->getKey(),
            'role_id' => $this->customerRoleId(),
        ], [
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function userHasCustomerRole(User $user): bool
    {
        return DB::table('auth_user_roles')
            ->join('auth_roles', 'auth_roles.id', '=', 'auth_user_roles.role_id')
            ->where('auth_user_roles.user_id', $user->getKey())
            ->where('auth_roles.slug', 'customer')
            ->where('auth_roles.status', 'active')
            ->whereNull('auth_roles.deleted_at')
            ->exists();
    }

    private function customerRoleId(): int
    {
        DB::table('auth_roles')->updateOrInsert([
            'slug' => 'customer',
        ], [
            'uuid' => (string) Str::uuid(),
            'name' => 'Customer',
            'description' => 'Customer role',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table('auth_roles')->where('slug', 'customer')->value('id');
    }
}
