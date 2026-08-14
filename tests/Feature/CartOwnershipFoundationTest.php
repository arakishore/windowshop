<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PDO;
use Tests\TestCase;

class CartOwnershipFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase()
    {
        $pdo = DB::connection()->getPdo();

        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $pdo->sqliteCreateCollation(
                'utf8mb4_unicode_ci',
                fn (string $left, string $right): int => strcmp($left, $right),
            );
        }
    }

    public function test_carts_schema_uses_global_user_id_not_merchant_customer_id(): void
    {
        $this->assertTrue(Schema::hasColumn('carts', 'user_id'));
        $this->assertFalse(Schema::hasColumn('carts', 'customer_id'));

        $foreignKeys = DB::select('PRAGMA foreign_key_list(carts)');
        $userForeignKey = collect($foreignKeys)->first(fn (object $key): bool => $key->from === 'user_id');

        $this->assertNotNull($userForeignKey);
        $this->assertSame('users', $userForeignKey->table);
        $this->assertSame('id', $userForeignKey->to);
        $this->assertSame('SET NULL', $userForeignKey->on_delete);
    }

    public function test_logged_in_cart_can_belong_to_user_without_merchant_customer(): void
    {
        $user = $this->userFixture('cart-owner@example.test', '9876543210');

        $cart = Cart::query()->create([
            'user_id' => $user->getKey(),
        ]);

        $this->assertSame($user->getKey(), $cart->user_id);
        $this->assertTrue($cart->user->is($user));
        $this->assertTrue($user->carts()->whereKey($cart->getKey())->exists());
        $this->assertDatabaseMissing('merchant_customers', [
            'user_id' => $user->getKey(),
        ]);
    }

    public function test_guest_cart_with_session_token_remains_valid(): void
    {
        $cart = Cart::query()->create([
            'user_id' => null,
            'session_token' => 'guest-session-token',
        ]);

        $this->assertNull($cart->user_id);
        $this->assertSame('guest-session-token', $cart->session_token);
        $this->assertDatabaseHas('carts', [
            'id' => $cart->getKey(),
            'user_id' => null,
            'session_token' => 'guest-session-token',
        ]);
    }

    public function test_force_deleting_user_nulls_cart_user_id(): void
    {
        $user = $this->userFixture('cart-delete@example.test', '9876543211');
        $cart = Cart::query()->create([
            'user_id' => $user->getKey(),
        ]);

        $user->forceDelete();

        $this->assertNull($cart->refresh()->user_id);
    }

    private function userFixture(string $email, string $mobile): User
    {
        return User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Cart User',
            'email' => $email,
            'mobile' => $mobile,
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);
    }
}
