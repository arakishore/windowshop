<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminRole
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('admin.login');
        }

        $hasAdminRole = DB::table('auth_user_roles')
            ->join('auth_roles', 'auth_roles.id', '=', 'auth_user_roles.role_id')
            ->where('auth_user_roles.user_id', $user->getKey())
            ->whereIn('auth_roles.slug', ['super_admin', 'admin'])
            ->where('auth_roles.status', 'active')
            ->whereNull('auth_roles.deleted_at')
            ->exists();

        abort_unless($hasAdminRole, 403);

        return $next($request);
    }
}
