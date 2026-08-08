<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PDO;
use Tests\TestCase;

class AdminAuthRedirectTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $pdo = DB::connection()->getPdo();

        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $pdo->sqliteCreateCollation(
                'utf8mb4_unicode_ci',
                fn (string $left, string $right): int => strcmp($left, $right),
            );
        }
    }

    public function test_authenticated_admin_is_redirected_from_login_to_dashboard(): void
    {
        $admin = $this->userWithRole('admin');

        $this
            ->actingAs($admin)
            ->get(route('admin.login'))
            ->assertRedirect(route('admin.dashboard'));

        $this
            ->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk();
    }

    public function test_authenticated_user_without_admin_role_cannot_view_admin_dashboard(): void
    {
        $merchant = $this->userWithRole('merchant');

        $this
            ->actingAs($merchant)
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }

    private function userWithRole(string $roleSlug): User
    {
        $user = User::factory()->create();

        $roleId = DB::table('auth_roles')->where('slug', $roleSlug)->value('id')
            ?? DB::table('auth_roles')->insertGetId([
                'uuid' => fake()->uuid(),
                'name' => str($roleSlug)->replace('_', ' ')->title()->toString(),
                'slug' => $roleSlug,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        DB::table('auth_user_roles')->insert([
            'user_id' => $user->getKey(),
            'role_id' => $roleId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $user;
    }
}
