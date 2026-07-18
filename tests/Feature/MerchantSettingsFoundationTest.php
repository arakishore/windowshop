<?php

namespace Tests\Feature;

use App\Models\MerchantProfile;
use App\Models\MerchantSetting;
use App\Models\User;
use App\Services\Merchant\MerchantSettingsInitializer;
use App\Services\Merchant\MerchantSettingsService;
use Database\Seeders\MerchantSettingsSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use InvalidArgumentException;
use PDO;
use Tests\TestCase;

class MerchantSettingsFoundationTest extends TestCase
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

    public function test_new_merchants_receive_complete_default_settings_immediately(): void
    {
        $merchant = $this->merchantFixture('Settings Merchant');
        $expectedCount = collect($this->initializer()->defaults())->sum(fn (array $settings): int => count($settings));

        $this->assertSame($expectedCount, MerchantSetting::query()->where('merchant_id', $merchant->getKey())->count());
        $this->assertSame('nearest', $this->settings()->get($merchant->getKey(), 'pos', 'cash_rounding.method'));
        $this->assertSame('cash', $this->settings()->get($merchant->getKey(), 'pos', 'cash_rounding.apply_to'));
        $this->assertTrue($this->settings()->get($merchant->getKey(), 'pos', 'order.allow_order_discount'));
        $this->assertTrue($this->settings()->get($merchant->getKey(), 'pos', 'order.allow_item_discount'));
        $this->assertTrue($this->settings()->get($merchant->getKey(), 'pos', 'receipt.show_gst_number'));
        $this->assertTrue($this->settings()->get($merchant->getKey(), 'pos', 'receipt.show_qr_code'));
        $this->assertTrue($this->settings()->get($merchant->getKey(), 'pos', 'receipt.show_tax_breakdown'));
        $this->assertFalse($this->settings()->get($merchant->getKey(), 'pos', 'receipt.line_item.show_sku'));
        $this->assertFalse($this->settings()->get($merchant->getKey(), 'pos', 'receipt.line_item.show_hsn_code'));
        $this->assertFalse($this->settings()->get($merchant->getKey(), 'pos', 'receipt.line_item.show_hsn_summary'));
        $this->assertSame('Thank you for shopping with us.', $this->settings()->get($merchant->getKey(), 'pos', 'receipt.footer'));
        $this->assertSame('', $this->settings()->get($merchant->getKey(), 'pos', 'receipt.return_policy'));
        $this->assertFalse($this->settings()->get($merchant->getKey(), 'payment', 'allow_credit'));

        $this->assertDatabaseHas('merchant_settings', [
            'merchant_id' => $merchant->getKey(),
            'group' => 'pos',
            'setting_key' => 'cash_rounding.method',
            'setting_type' => MerchantSetting::TYPE_STRING,
        ]);
        $this->assertDatabaseMissing('merchant_settings', [
            'merchant_id' => $merchant->getKey(),
            'group' => 'pos',
            'setting_key' => 'product.search.mode',
        ]);
        $this->assertDatabaseMissing('merchant_settings', [
            'merchant_id' => $merchant->getKey(),
            'group' => 'pos',
            'setting_key' => 'cart.auto_clear_after_sale',
        ]);
        $this->assertDatabaseMissing('merchant_settings', [
            'merchant_id' => $merchant->getKey(),
            'group' => 'pos',
            'setting_key' => 'cash_rounding.precision',
        ]);
        $this->assertDatabaseMissing('merchant_settings', [
            'merchant_id' => $merchant->getKey(),
            'group' => 'pos',
            'setting_key' => 'cash_rounding.enabled',
        ]);
        $this->assertDatabaseMissing('merchant_settings', [
            'merchant_id' => $merchant->getKey(),
            'group' => 'order',
            'setting_key' => 'default_status',
        ]);
        $this->assertDatabaseMissing('merchant_settings', [
            'merchant_id' => $merchant->getKey(),
            'group' => 'product',
            'setting_key' => 'barcode.type',
        ]);
    }

    public function test_settings_are_unique_per_merchant_group_and_key(): void
    {
        $merchant = $this->merchantFixture('Unique Settings Merchant');

        $this->settings()->set($merchant->getKey(), 'loyalty', 'enabled', true);
        $this->settings()->set($merchant->getKey(), 'inventory', 'enabled', false);

        $this->assertTrue($this->settings()->get($merchant->getKey(), 'loyalty', 'enabled'));
        $this->assertFalse($this->settings()->get($merchant->getKey(), 'inventory', 'enabled'));

        $this->expectException(QueryException::class);

        DB::table('merchant_settings')->insert([
            'merchant_id' => $merchant->getKey(),
            'group' => 'loyalty',
            'setting_key' => 'enabled',
            'setting_value' => '1',
            'setting_type' => MerchantSetting::TYPE_BOOLEAN,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_service_sets_gets_has_and_returns_typed_values(): void
    {
        $merchant = $this->merchantFixture('Typed Settings Merchant');

        $this->settings()->set($merchant->getKey(), 'inventory', 'allow_negative_stock', false);
        $this->settings()->set($merchant->getKey(), 'payment', 'default_method', 'cash');
        $this->settings()->set($merchant->getKey(), 'invoice', 'copy_count', 2);
        $this->settings()->set($merchant->getKey(), 'invoice', 'rounding_adjustment', 0.5);
        $this->settings()->set($merchant->getKey(), 'invoice', 'options', ['print_logo' => true]);

        $this->assertTrue($this->settings()->has($merchant->getKey(), 'invoice', 'copy_count'));
        $this->assertFalse($this->settings()->get($merchant->getKey(), 'inventory', 'allow_negative_stock'));
        $this->assertSame('cash', $this->settings()->get($merchant->getKey(), 'payment', 'default_method'));
        $this->assertSame(2, $this->settings()->get($merchant->getKey(), 'invoice', 'copy_count'));
        $this->assertSame(0.5, $this->settings()->get($merchant->getKey(), 'invoice', 'rounding_adjustment'));
        $this->assertSame(['print_logo' => true], $this->settings()->get($merchant->getKey(), 'invoice', 'options'));
        $this->assertSame('fallback', $this->settings()->get($merchant->getKey(), 'missing', 'setting', 'fallback'));

        $invoice = $this->settings()->all($merchant->getKey(), 'invoice');
        $all = $this->settings()->all($merchant->getKey());

        $this->assertSame(2, $invoice['copy_count']);
        $this->assertSame(['print_logo' => true], $invoice['options']);
        $this->assertArrayHasKey('invoice.copy_count', $all->all());
        $this->assertArrayHasKey('pos.receipt.footer', $all->all());
    }

    public function test_seed_defaults_for_existing_merchants_is_idempotent(): void
    {
        $merchant = $this->merchantFixture('Existing Settings Merchant');
        $expectedCount = collect($this->initializer()->defaults())->sum(fn (array $settings): int => count($settings));

        MerchantSetting::query()
            ->where('merchant_id', $merchant->getKey())
            ->delete();

        $this->seed(MerchantSettingsSeeder::class);
        $this->seed(MerchantSettingsSeeder::class);

        $this->assertSame($expectedCount, MerchantSetting::query()->where('merchant_id', $merchant->getKey())->count());
        $this->assertSame('nearest', $this->settings()->get($merchant->getKey(), 'pos', 'cash_rounding.method'));
    }

    public function test_initializer_creates_missing_defaults_without_overwriting_saved_settings(): void
    {
        $merchant = $this->merchantFixture('Repair Settings Merchant');

        $this->settings()->set($merchant->getKey(), 'pos', 'receipt.footer', 'Custom footer');
        $this->settings()->set($merchant->getKey(), 'pos', 'receipt.show_barcode', true);
        MerchantSetting::query()
            ->where('merchant_id', $merchant->getKey())
            ->where('group', 'payment')
            ->where('setting_key', 'allow_card')
            ->delete();

        $this->initializer()->initialize($merchant->getKey());

        $this->assertSame('Custom footer', $this->settings()->get($merchant->getKey(), 'pos', 'receipt.footer'));
        $this->assertTrue($this->settings()->get($merchant->getKey(), 'pos', 'receipt.show_barcode'));
        $this->assertTrue($this->settings()->get($merchant->getKey(), 'payment', 'allow_card'));
    }

    public function test_cash_rounding_settings_reject_unsupported_values(): void
    {
        $merchant = $this->merchantFixture('Validation Settings Merchant');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cash rounding method must be nearest, up, or down.');

        $this->settings()->set($merchant->getKey(), 'pos', 'cash_rounding.method', 'sideways');
    }

    public function test_merchant_can_view_settings_page(): void
    {
        $merchant = $this->merchantFixture('Settings Page Merchant');
        $this->assignMerchantRole($merchant->user);

        $this->actingAs($merchant->user)
            ->get(route('merchant.settings.edit'))
            ->assertOk()
            ->assertSee('POS')
            ->assertSee('Cash Rounding')
            ->assertSee('Example: Rs 1043.28 becomes Rs 1043.00.')
            ->assertSee('UPI')
            ->assertDontSee('Cash on Delivery')
            ->assertSee('Receipts')
            ->assertSee('What to show')
            ->assertSee('GST Number')
            ->assertSee('Customer name + phone')
            ->assertSee('Tax breakdown')
            ->assertSee('QR Code')
            ->assertSee('Line item details')
            ->assertSee('SKU under each item')
            ->assertSee('HSN code under each item')
            ->assertSee('Receipt text')
            ->assertSee('Return policy')
            ->assertSee('Live Preview')
            ->assertSee('Demo Retail Store')
            ->assertSee('POS-1001')
            ->assertSee('Accepted Payment Methods')
            ->assertSee('Thank you for shopping with us.')
            ->assertSee('Save Changes');
    }

    public function test_merchant_can_update_settings_from_page(): void
    {
        $merchant = $this->merchantFixture('Settings Update Merchant');
        $this->assignMerchantRole($merchant->user);

        $response = $this->actingAs($merchant->user)
            ->put(route('merchant.settings.update'), [
                'settings' => [
                    'pos' => [
                        'cash_rounding.method' => 'down',
                        'cash_rounding.apply_to' => 'all',
                        'receipt.show_shop_name' => '1',
                        'receipt.show_address' => '1',
                        'receipt.show_phone' => '1',
                        'receipt.show_gst_number' => '0',
                        'receipt.show_customer' => '1',
                        'receipt.show_cashier' => '1',
                        'receipt.show_order_number' => '1',
                        'receipt.show_barcode' => '1',
                        'receipt.show_qr_code' => '0',
                        'receipt.show_tax_breakdown' => '0',
                        'receipt.line_item.show_sku' => '1',
                        'receipt.line_item.show_hsn_code' => '1',
                        'receipt.line_item.show_hsn_summary' => '1',
                        'receipt.footer' => 'Visit again.',
                        'receipt.return_policy' => 'No returns after 7 days.',
                        'held_order.expiry_days' => '15',
                        'order.allow_order_discount' => '1',
                        'order.allow_item_discount' => '0',
                    ],
                    'inventory' => [
                        'allow_negative_stock' => '0',
                        'show_low_stock_warning' => '1',
                        'low_stock_default' => '3',
                    ],
                    'product' => [
                        'barcode.auto_generate' => '1',
                        'default_visibility' => 'public',
                    ],
                    'payment' => [
                        'default_payment_method' => 'upi',
                        'allow_cash' => '1',
                        'allow_upi' => '1',
                        'allow_card' => '0',
                        'allow_bank_transfer' => '0',
                        'allow_credit' => '0',
                    ],
                ],
            ]);

        $response
            ->assertRedirect()
            ->assertSessionHas('success', 'Merchant settings updated successfully.');

        $this->assertSame('down', $this->settings()->get($merchant->getKey(), 'pos', 'cash_rounding.method'));
        $this->assertSame('Visit again.', $this->settings()->get($merchant->getKey(), 'pos', 'receipt.footer'));
        $this->assertFalse($this->settings()->get($merchant->getKey(), 'pos', 'receipt.show_gst_number'));
        $this->assertTrue($this->settings()->get($merchant->getKey(), 'pos', 'receipt.show_barcode'));
        $this->assertFalse($this->settings()->get($merchant->getKey(), 'pos', 'receipt.show_qr_code'));
        $this->assertFalse($this->settings()->get($merchant->getKey(), 'pos', 'receipt.show_tax_breakdown'));
        $this->assertTrue($this->settings()->get($merchant->getKey(), 'pos', 'receipt.line_item.show_sku'));
        $this->assertTrue($this->settings()->get($merchant->getKey(), 'pos', 'receipt.line_item.show_hsn_code'));
        $this->assertTrue($this->settings()->get($merchant->getKey(), 'pos', 'receipt.line_item.show_hsn_summary'));
        $this->assertSame('No returns after 7 days.', $this->settings()->get($merchant->getKey(), 'pos', 'receipt.return_policy'));
        $this->assertFalse($this->settings()->get($merchant->getKey(), 'pos', 'order.allow_item_discount'));
        $this->assertFalse($this->settings()->get($merchant->getKey(), 'payment', 'allow_card'));
    }

    public function test_saved_settings_are_not_reset_when_settings_page_reloads(): void
    {
        $merchant = $this->merchantFixture('Settings Reload Merchant');
        $this->assignMerchantRole($merchant->user);

        $this->settings()->set($merchant->getKey(), 'pos', 'receipt.show_barcode', true);
        $this->settings()->set($merchant->getKey(), 'pos', 'receipt.footer', 'Saved footer');

        $this->actingAs($merchant->user)
            ->get(route('merchant.settings.edit'))
            ->assertOk();

        $this->assertTrue($this->settings()->get($merchant->getKey(), 'pos', 'receipt.show_barcode'));
        $this->assertSame('Saved footer', $this->settings()->get($merchant->getKey(), 'pos', 'receipt.footer'));
    }

    public function test_cash_rounding_apply_to_accepts_supported_payment_method_sets(): void
    {
        $merchant = $this->merchantFixture('Rounding Apply Merchant');

        $this->settings()->set($merchant->getKey(), 'pos', 'cash_rounding.apply_to', 'cash,upi,card');

        $this->assertSame(
            'cash,upi,card',
            $this->settings()->get($merchant->getKey(), 'pos', 'cash_rounding.apply_to'),
        );
    }

    public function test_cash_rounding_apply_to_rejects_cod_for_pos(): void
    {
        $merchant = $this->merchantFixture('Rounding COD Merchant');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cash rounding apply_to contains an unsupported payment method.');

        $this->settings()->set($merchant->getKey(), 'pos', 'cash_rounding.apply_to', 'cash,cod');
    }

    public function test_initializer_removes_obsolete_settings(): void
    {
        $merchant = $this->merchantFixture('Obsolete Settings Merchant');

        foreach (['product.search.mode', 'cart.auto_clear_after_sale', 'cash_rounding.precision', 'cash_rounding.enabled', 'receipt.show_logo', 'receipt.header_text'] as $key) {
            MerchantSetting::query()->create([
                'merchant_id' => $merchant->getKey(),
                'group' => 'pos',
                'setting_key' => $key,
                'setting_value' => match ($key) {
                    'cash_rounding.enabled',
                    'cart.auto_clear_after_sale' => '1',
                    'cash_rounding.precision' => '5',
                    default => 'smart',
                },
                'setting_type' => match ($key) {
                    'cash_rounding.enabled',
                    'cart.auto_clear_after_sale' => MerchantSetting::TYPE_BOOLEAN,
                    'cash_rounding.precision' => MerchantSetting::TYPE_INTEGER,
                    default => MerchantSetting::TYPE_STRING,
                },
            ]);
        }

        foreach (['default_status', 'allow_order_discount', 'allow_item_discount'] as $key) {
            MerchantSetting::query()->create([
                'merchant_id' => $merchant->getKey(),
                'group' => 'order',
                'setting_key' => $key,
                'setting_value' => $key === 'default_status' ? 'completed' : '1',
                'setting_type' => $key === 'default_status'
                    ? MerchantSetting::TYPE_STRING
                    : MerchantSetting::TYPE_BOOLEAN,
            ]);
        }

        MerchantSetting::query()->create([
            'merchant_id' => $merchant->getKey(),
            'group' => 'product',
            'setting_key' => 'barcode.type',
            'setting_value' => 'code128',
            'setting_type' => MerchantSetting::TYPE_STRING,
        ]);

        $this->initializer()->initialize($merchant->getKey());

        foreach (['product.search.mode', 'cart.auto_clear_after_sale', 'cash_rounding.precision', 'cash_rounding.enabled', 'receipt.show_logo', 'receipt.header_text'] as $key) {
            $this->assertDatabaseMissing('merchant_settings', [
                'merchant_id' => $merchant->getKey(),
                'group' => 'pos',
                'setting_key' => $key,
            ]);
        }

        foreach (['default_status', 'allow_order_discount', 'allow_item_discount'] as $key) {
            $this->assertDatabaseMissing('merchant_settings', [
                'merchant_id' => $merchant->getKey(),
                'group' => 'order',
                'setting_key' => $key,
            ]);
        }

        $this->assertDatabaseMissing('merchant_settings', [
            'merchant_id' => $merchant->getKey(),
            'group' => 'product',
            'setting_key' => 'barcode.type',
        ]);
    }

    private function settings(): MerchantSettingsService
    {
        return app(MerchantSettingsService::class);
    }

    private function initializer(): MerchantSettingsInitializer
    {
        return app(MerchantSettingsInitializer::class);
    }

    private function merchantFixture(string $businessName): MerchantProfile
    {
        $user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => $businessName.' Owner',
            'email' => Str::slug($businessName).'-'.Str::random(6).'@example.test',
            'mobile' => '90000'.random_int(10000, 99999),
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);

        return MerchantProfile::query()->create([
            'user_id' => $user->getKey(),
            'business_name' => $businessName.' '.Str::random(4),
            'verification_status' => 'approved',
            'status' => 'active',
        ]);
    }

    private function assignMerchantRole(User $user): void
    {
        $roleId = DB::table('auth_roles')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'name' => 'Merchant',
            'slug' => 'merchant',
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
    }
}
