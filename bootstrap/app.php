<?php

use App\Http\Middleware\EnsureAdminRole;
use App\Http\Middleware\EnsureMerchantActiveShop;
use App\Http\Middleware\EnsureMerchantRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: [
            __DIR__.'/../routes/web.php',
            __DIR__.'/../routes/merchant.php',
        ],
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        __DIR__.'/../app/Console/Commands',
    ])
    ->withMiddleware(function (Middleware $middleware): void {

        // Redirect unauthenticated users to the correct login page
        $middleware->redirectGuestsTo(function (Request $request): string {
            if ($request->is('merchant') || $request->is('merchant/*')) {
                return route('merchant.login');
            }

            return route('admin.login');
        });

        // Redirect already-authenticated users away from login pages by role.
        $middleware->redirectUsersTo(function (Request $request): string {
            $user = $request->user();

            if ($user === null) {
                return route('admin.login');
            }

            $roles = DB::table('auth_user_roles')
                ->join('auth_roles', 'auth_roles.id', '=', 'auth_user_roles.role_id')
                ->where('auth_user_roles.user_id', $user->getKey())
                ->where('auth_roles.status', 'active')
                ->whereNull('auth_roles.deleted_at')
                ->pluck('auth_roles.slug');

            if ($roles->contains(fn (string $slug): bool => in_array($slug, ['super_admin', 'admin'], true))) {
                return route('admin.dashboard');
            }

            if ($roles->contains('merchant')) {
                return route('merchant.profile.edit');
            }

            return route('admin.logout');
        });

        $middleware->alias([
            'admin.role' => EnsureAdminRole::class,
            'merchant.active_shop' => EnsureMerchantActiveShop::class,
            'merchant.role' => EnsureMerchantRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->create();
