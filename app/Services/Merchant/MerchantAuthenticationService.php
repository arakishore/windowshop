<?php

namespace App\Services\Merchant;

use App\Enums\MerchantStatus;
use App\Models\MerchantProfile;
use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MerchantAuthenticationService
{
    /**
     * @param array{login: string, password: string} $credentials
     * @return array{user: User, merchant: MerchantProfile}
     *
     * @throws ValidationException
     */
    public function authenticateWeb(Request $request, array $credentials, bool $remember): array
    {
        $identifier = $this->normalizeIdentifier($credentials['login']);
        $field = $this->identifierField($identifier);
        $throttleKey = Str::transliterate($identifier).'|'.$request->ip();
        $candidate = $this->findUserByIdentifier($identifier, $field);

        if (RateLimiter::tooManyAttempts(
            $throttleKey,
            config('auth_security.merchant_login_max_attempts'),
        )) {
            $this->recordLoginHistory($request, $candidate, 'blocked', 'too_many_attempts');
            event(new Lockout($request));

            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'login' => "Too many login attempts. Please try again in {$seconds} seconds.",
            ]);
        }

        $authenticated = $this->isActiveUserRecord($candidate)
            && Auth::attempt([
                $field => $identifier,
                'password' => $credentials['password'],
                'status' => 'active',
            ], $remember);

        if (! $authenticated) {
            $this->hitRateLimiter($throttleKey);
            $this->recordLoginHistory(
                $request,
                $candidate,
                'failed',
                $this->failureReason($candidate),
            );

            throw $this->invalidCredentials();
        }

        /** @var User $user */
        $user = Auth::user();
        $merchant = $this->activeMerchantForUser($user);

        if ($merchant === null) {
            Auth::logout();
            $this->hitRateLimiter($throttleKey);
            $this->recordLoginHistory(
                $request,
                $user,
                'blocked',
                $this->merchantFailureReason($user),
            );

            throw $this->invalidCredentials();
        }

        RateLimiter::clear($throttleKey);

        return [
            'user' => $user,
            'merchant' => $merchant,
        ];
    }

    public function activeMerchantForUser(User $user): ?MerchantProfile
    {
        if (! $this->hasMerchantRole($user->getKey())) {
            return null;
        }

        return MerchantProfile::query()
            ->where('user_id', $user->getKey())
            ->where('status', MerchantStatus::ACTIVE->value)
            ->whereNull('deleted_at')
            ->where('verification_status', '!=', 'suspended')
            ->first();
    }

    /**
     * @return Collection<int, \App\Models\Shop>
     */
    public function activeShopsForMerchant(MerchantProfile $merchant): Collection
    {
        return $merchant->shops()
            ->with('city')
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get();
    }

    public function recordSuccessfulWebLogin(Request $request, User $user): void
    {
        $now = now();

        $user->forceFill([
            'last_login_at' => $now,
            'last_login_ip' => $request->ip(),
        ])->save();

        DB::transaction(function () use ($request, $user, $now): void {
            DB::table('auth_user_sessions')
                ->where('user_id', $user->getKey())
                ->where('is_current', true)
                ->update([
                    'is_current' => false,
                    'updated_at' => $now,
                ]);

            DB::table('auth_user_sessions')->insert([
                'uuid' => (string) Str::uuid(),
                'user_id' => $user->getKey(),
                'session_id' => $request->session()->getId(),
                'guard_name' => 'merchant_web',
                ...$this->clientDetails($request),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'login_at' => $now,
                'last_activity_at' => $now,
                'is_current' => true,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $this->recordLoginHistory($request, $user, 'success');
        });
    }

    public function logoutWeb(Request $request): void
    {
        /** @var User|null $user */
        $user = Auth::user();
        $sessionId = $request->session()->getId();
        $now = now();

        if ($user !== null) {
            DB::transaction(function () use ($request, $user, $sessionId, $now): void {
                DB::table('auth_user_sessions')
                    ->where('user_id', $user->getKey())
                    ->where('session_id', $sessionId)
                    ->where('is_active', true)
                    ->update([
                        'logout_at' => $now,
                        'is_current' => false,
                        'is_active' => false,
                        'logout_reason' => 'manual',
                        'updated_at' => $now,
                    ]);

                $this->recordLoginHistory(
                    $request,
                    $user,
                    'logout',
                    logoutAt: $now,
                );
            });
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    private function normalizeIdentifier(string $identifier): string
    {
        $identifier = trim($identifier);

        return filter_var($identifier, FILTER_VALIDATE_EMAIL)
            ? Str::lower($identifier)
            : $identifier;
    }

    private function identifierField(string $identifier): string
    {
        return filter_var($identifier, FILTER_VALIDATE_EMAIL) ? 'email' : 'mobile';
    }

    private function findUserByIdentifier(string $identifier, string $field): ?object
    {
        return DB::table('users')
            ->where($field, $identifier)
            ->first();
    }

    private function isActiveUserRecord(?object $user): bool
    {
        return $user !== null
            && $user->deleted_at === null
            && $user->status === 'active';
    }

    private function hasMerchantRole(int $userId): bool
    {
        return DB::table('auth_user_roles')
            ->join('auth_roles', 'auth_roles.id', '=', 'auth_user_roles.role_id')
            ->where('auth_user_roles.user_id', $userId)
            ->where('auth_roles.slug', 'merchant')
            ->where('auth_roles.status', 'active')
            ->whereNull('auth_roles.deleted_at')
            ->exists();
    }

    private function failureReason(?object $user): string
    {
        if ($user === null) {
            return 'invalid_credentials';
        }

        if ($user->deleted_at !== null || $user->status === 'deleted') {
            return 'deleted_user';
        }

        return match ($user->status) {
            'inactive' => 'inactive_user',
            'suspended' => 'suspended_user',
            default => 'invalid_credentials',
        };
    }

    private function merchantFailureReason(User $user): string
    {
        if (! $this->hasMerchantRole($user->getKey())) {
            return 'merchant_role_required';
        }

        $merchant = MerchantProfile::withTrashed()
            ->where('user_id', $user->getKey())
            ->first();

        if ($merchant === null) {
            return 'merchant_profile_required';
        }

        if ($merchant->deleted_at !== null || $merchant->status === 'deleted') {
            return 'deleted_merchant';
        }

        if ($merchant->status === MerchantStatus::INACTIVE->value) {
            return 'inactive_merchant';
        }

        if ($merchant->status === MerchantStatus::SUSPENDED->value || $merchant->verification_status === 'suspended') {
            return 'suspended_merchant';
        }

        return 'merchant_access_blocked';
    }

    private function hitRateLimiter(string $throttleKey): void
    {
        RateLimiter::hit(
            $throttleKey,
            config('auth_security.merchant_login_decay_seconds'),
        );
    }

    private function invalidCredentials(): ValidationException
    {
        return ValidationException::withMessages([
            'login' => 'These credentials do not match our records.',
        ]);
    }

    private function recordLoginHistory(
        Request $request,
        ?object $user,
        string $status,
        ?string $failureReason = null,
        ?Carbon $logoutAt = null,
    ): void {
        $now = now();
        $submittedIdentifier = $request->string('login')->toString();
        $submittedEmail = filter_var($submittedIdentifier, FILTER_VALIDATE_EMAIL)
            ? Str::lower($submittedIdentifier)
            : null;
        $submittedMobile = $submittedEmail === null && $submittedIdentifier !== ''
            ? $submittedIdentifier
            : null;
        $userId = $user instanceof User ? $user->getKey() : ($user->id ?? null);

        DB::table('auth_user_login_history')->insert([
            'uuid' => (string) Str::uuid(),
            'user_id' => $userId,
            'email' => $submittedEmail ?? ($user->email ?? null),
            'mobile' => $submittedMobile ?? ($user->mobile ?? null),
            'guard_name' => 'merchant_web',
            'login_identifier' => $submittedIdentifier !== '' ? $submittedIdentifier : ($user->email ?? null),
            'status' => $status,
            'failure_reason' => $failureReason,
            'session_id' => $request->hasSession() ? $request->session()->getId() : null,
            ...$this->clientDetails($request),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'attempted_at' => $now,
            'logout_at' => $logoutAt,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * @return array{
     *     device_name: string,
     *     device_type: string,
     *     browser: string,
     *     browser_version: string|null,
     *     platform: string,
     *     platform_version: string|null
     * }
     */
    private function clientDetails(Request $request): array
    {
        $userAgent = $request->userAgent() ?? '';

        [$browser, $browserVersion] = match (true) {
            preg_match('/Edg\/([\d.]+)/i', $userAgent, $matches) === 1 => ['Edge', $matches[1]],
            preg_match('/OPR\/([\d.]+)/i', $userAgent, $matches) === 1 => ['Opera', $matches[1]],
            preg_match('/Chrome\/([\d.]+)/i', $userAgent, $matches) === 1 => ['Chrome', $matches[1]],
            preg_match('/Firefox\/([\d.]+)/i', $userAgent, $matches) === 1 => ['Firefox', $matches[1]],
            preg_match('/Version\/([\d.]+).*Safari/i', $userAgent, $matches) === 1 => ['Safari', $matches[1]],
            default => ['Unknown', null],
        };

        [$platform, $platformVersion] = match (true) {
            preg_match('/Android\s([\d.]+)/i', $userAgent, $matches) === 1 => ['Android', $matches[1]],
            preg_match('/(?:iPhone OS|CPU OS)\s([\d_]+)/i', $userAgent, $matches) === 1 => ['iOS', str_replace('_', '.', $matches[1])],
            preg_match('/Windows NT\s([\d.]+)/i', $userAgent, $matches) === 1 => ['Windows', $matches[1]],
            preg_match('/Mac OS X\s([\d_]+)/i', $userAgent, $matches) === 1 => ['macOS', str_replace('_', '.', $matches[1])],
            str_contains(Str::lower($userAgent), 'linux') => ['Linux', null],
            default => ['Unknown', null],
        };

        $deviceType = match (true) {
            preg_match('/bot|crawler|spider|slurp|bingpreview/i', $userAgent) === 1 => 'bot',
            preg_match('/ipad|tablet/i', $userAgent) === 1 => 'tablet',
            preg_match('/mobile|iphone|ipod|android/i', $userAgent) === 1 => 'mobile',
            $userAgent !== '' => 'desktop',
            default => 'unknown',
        };

        return [
            'device_name' => Str::limit($browser.' on '.$platform, 150, ''),
            'device_type' => $deviceType,
            'browser' => $browser,
            'browser_version' => $browserVersion,
            'platform' => $platform,
            'platform_version' => $platformVersion,
        ];
    }
}
