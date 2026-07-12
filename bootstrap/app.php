<?php

use App\Http\Middleware\EnsureAdminRole;
use App\Http\Middleware\EnsureMerchantActiveShop;
use App\Http\Middleware\EnsureMerchantRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: [
            __DIR__.'/../routes/web.php',
            __DIR__.'/../routes/merchant.php',
        ],
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {

        // Redirect unauthenticated users to the correct login page
        $middleware->redirectGuestsTo(function (Request $request): string {
            if ($request->is('merchant') || $request->is('merchant/*')) {
                return route('merchant.login');
            }

            return route('admin.login');
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
