<?php

namespace App\Services\Storefront;

use App\Models\Customer;
use App\Models\User;
use App\Services\Customer\CustomerIdentityResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StorefrontCustomerContext
{
    public const ACTIVE_ROLE_SESSION_KEY = 'active_role_id';
    private const CUSTOMER_ROLE_SLUG = 'customer';
    private const BACKOFFICE_ROLE_SLUGS = ['super_admin', 'admin', 'merchant'];

    public function __construct(
        private readonly CustomerIdentityResolver $customerIdentityResolver,
    ) {
    }

    public function user(Request $request): ?User
    {
        $user = $request->user();

        if (! $user instanceof User || $user->status !== 'active' || $user->deleted_at !== null) {
            return null;
        }

        $activeRoleId = $request->session()->get(self::ACTIVE_ROLE_SESSION_KEY);

        if ($activeRoleId !== null && $activeRoleId !== '') {
            return $this->activeRoleSlug((int) $activeRoleId) === self::CUSTOMER_ROLE_SLUG
                && $this->userHasActiveRole($user, self::CUSTOMER_ROLE_SLUG)
                    ? $user
                    : null;
        }

        return $this->isCustomerOnlyUser($user) ? $user : null;
    }

    public function userId(Request $request): ?int
    {
        return $this->user($request)?->getKey();
    }

    public function customer(Request $request): ?Customer
    {
        $user = $this->user($request);

        return $user instanceof User
            ? $this->customerIdentityResolver->resolveOrCreateForUser($user)
            : null;
    }

    private function activeRoleSlug(int $roleId): ?string
    {
        $slug = DB::table('auth_roles')
            ->where('id', $roleId)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->value('slug');

        return is_string($slug) ? $slug : null;
    }

    private function userHasActiveRole(User $user, string $slug): bool
    {
        return DB::table('auth_user_roles')
            ->join('auth_roles', 'auth_roles.id', '=', 'auth_user_roles.role_id')
            ->where('auth_user_roles.user_id', $user->getKey())
            ->where('auth_roles.slug', $slug)
            ->where('auth_roles.status', 'active')
            ->whereNull('auth_roles.deleted_at')
            ->exists();
    }

    private function isCustomerOnlyUser(User $user): bool
    {
        $roleSlugs = DB::table('auth_user_roles')
            ->join('auth_roles', 'auth_roles.id', '=', 'auth_user_roles.role_id')
            ->where('auth_user_roles.user_id', $user->getKey())
            ->where('auth_roles.status', 'active')
            ->whereNull('auth_roles.deleted_at')
            ->pluck('auth_roles.slug')
            ->all();

        return in_array(self::CUSTOMER_ROLE_SLUG, $roleSlugs, true)
            && empty(array_intersect(self::BACKOFFICE_ROLE_SLUGS, $roleSlugs));
    }
}
