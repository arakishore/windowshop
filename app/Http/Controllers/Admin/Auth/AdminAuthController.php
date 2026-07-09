<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminAuthController extends Controller
{
    public function showLoginForm(): View
    {
        return view('admin.auth.login');
    }

    /**
     * @throws ValidationException
     */
    public function authenticate(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
        ]);

        $email = Str::lower($credentials['email']);
        $throttleKey = Str::transliterate($email).'|'.$request->ip();
        $candidate = DB::table('users')->where('email', $email)->first();

        if (RateLimiter::tooManyAttempts(
            $throttleKey,
            config('auth_security.admin_login_max_attempts'),
        )) {
            $this->recordLoginHistory($request, $candidate, 'blocked', 'too_many_attempts');
            event(new Lockout($request));

            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'email' => "Too many login attempts. Please try again in {$seconds} seconds.",
            ]);
        }

        $eligible = $candidate !== null
            && $candidate->deleted_at === null
            && $candidate->status === 'active';

        $authenticated = $eligible && Auth::attempt([
            'email' => $email,
            'password' => $credentials['password'],
            'status' => 'active',
        ], $request->boolean('remember'));

        if (! $authenticated) {
            RateLimiter::hit(
                $throttleKey,
                config('auth_security.admin_login_decay_seconds'),
            );
            $this->recordLoginHistory(
                $request,
                $candidate,
                'failed',
                $this->failureReason($candidate),
            );

            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        /** @var User $user */
        $user = Auth::user();

        if (! $this->hasAdminRole($user->getKey())) {
            Auth::logout();
            RateLimiter::hit(
                $throttleKey,
                config('auth_security.admin_login_decay_seconds'),
            );
            $this->recordLoginHistory($request, $user, 'blocked', 'admin_role_required');

            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        $request->session()->regenerate();
        RateLimiter::clear($throttleKey);

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
                'guard_name' => 'web',
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

        return redirect()->intended(route('admin.dashboard'));
    }

    public function logout(Request $request): RedirectResponse
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

        return redirect()
            ->route('admin.login')
            ->with('success', 'You have been logged out successfully.');
    }

    private function hasAdminRole(int $userId): bool
    {
        return DB::table('auth_user_roles')
            ->join('auth_roles', 'auth_roles.id', '=', 'auth_user_roles.role_id')
            ->where('auth_user_roles.user_id', $userId)
            ->whereIn('auth_roles.slug', ['super_admin', 'admin'])
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

    private function recordLoginHistory(
        Request $request,
        ?object $user,
        string $status,
        ?string $failureReason = null,
        ?Carbon $logoutAt = null,
    ): void {
        $now = now();
        $submittedEmail = $request->string('email')->toString();
        $userId = $user instanceof User ? $user->getKey() : ($user->id ?? null);

        DB::table('auth_user_login_history')->insert([
            'uuid' => (string) Str::uuid(),
            'user_id' => $userId,
            'email' => $submittedEmail !== '' ? $submittedEmail : ($user->email ?? null),
            'mobile' => $user->mobile ?? null,
            'guard_name' => 'web',
            'login_identifier' => $submittedEmail !== '' ? $submittedEmail : ($user->email ?? null),
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
