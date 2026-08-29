<?php

namespace Tests\Feature;

use App\Models\MerchantProfile;
use App\Models\PostalCode;
use App\Models\PostalCodeRestriction;
use App\Models\ProductCategory;
use App\Models\Shop;
use App\Models\ShopSetting;
use App\Models\User;
use App\Services\Checkout\ShopDeliveryQuoteService;
use App\Services\Delivery\ShopDeliveryServiceabilityService;
use App\Services\Merchant\ShopSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PDO;
use Tests\TestCase;

class ShopDeliveryServiceabilityTest extends TestCase
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

    public function test_local_only_allows_same_state_and_district_pin(): void
    {
        $shop = $this->shop('Nashik Shop', '422009');
        $this->postalCode('422009', district: 'NASHIK', state: 'MAHARASHTRA');
        $this->postalCode('422010', district: 'nashik', state: 'maharashtra');

        $result = app(ShopDeliveryServiceabilityService::class)->check($shop, '422010');

        $this->assertTrue($result['serviceable']);
        $this->assertSame(ShopDeliveryServiceabilityService::SCOPE_LOCAL_ONLY, $result['delivery_scope']);
    }

    public function test_local_only_rejects_destination_outside_shop_state_and_district(): void
    {
        $shop = $this->shop('Nashik Shop', '422009');
        $this->postalCode('422009', district: 'NASHIK', state: 'MAHARASHTRA');
        $this->postalCode('802301', district: 'BHOJPUR', state: 'BIHAR');

        $result = app(ShopDeliveryServiceabilityService::class)->check($shop, '802301');

        $this->assertFalse($result['serviceable']);
        $this->assertSame('outside_local_scope', $result['code']);
        $this->assertSame('Delivery is not available to this PIN code.', $result['message']);
    }

    public function test_nationwide_allows_shipping_enabled_pin_without_restriction(): void
    {
        $shop = $this->shop('Nationwide Shop', '422009');
        $this->shopSetting($shop, 'fulfillment', 'delivery_scope', 'nationwide', ShopSetting::TYPE_STRING);
        $this->postalCode('422009', district: 'NASHIK', state: 'MAHARASHTRA');
        $this->postalCode('802301', district: 'BHOJPUR', state: 'BIHAR');

        $result = app(ShopDeliveryServiceabilityService::class)->check($shop, '802301');

        $this->assertTrue($result['serviceable']);
        $this->assertSame(ShopDeliveryServiceabilityService::SCOPE_NATIONWIDE, $result['delivery_scope']);
    }

    public function test_nationwide_still_rejects_global_and_shop_restrictions(): void
    {
        $shop = $this->shop('Restricted Shop', '422009');
        $this->shopSetting($shop, 'fulfillment', 'delivery_scope', 'nationwide', ShopSetting::TYPE_STRING);
        $this->postalCode('422009', district: 'NASHIK', state: 'MAHARASHTRA');
        $this->postalCode('802301', district: 'BHOJPUR', state: 'BIHAR');

        PostalCodeRestriction::query()->create([
            'postal_code' => '802301',
            'reason' => 'Global restriction',
            'status' => PostalCodeRestriction::STATUS_ACTIVE,
        ]);

        $service = app(ShopDeliveryServiceabilityService::class);
        $global = $service->check($shop, '802301');
        $this->assertFalse($global['serviceable']);
        $this->assertSame('postal_restricted', $global['code']);
        $this->assertSame('Global restriction', $global['reason']);

        PostalCodeRestriction::query()->where('postal_code', '802301')->delete();
        PostalCodeRestriction::query()->create([
            'postal_code' => '802301',
            'merchant_id' => $shop->merchant_id,
            'shop_id' => $shop->getKey(),
            'reason' => 'Shop restriction',
            'status' => PostalCodeRestriction::STATUS_ACTIVE,
        ]);

        $shopRestricted = $service->check($shop, '802301');
        $this->assertFalse($shopRestricted['serviceable']);
        $this->assertSame('postal_restricted', $shopRestricted['code']);
        $this->assertSame('Shop restriction', $shopRestricted['reason']);
    }

    public function test_delivery_disabled_invalid_unknown_and_non_shipping_pins_are_unavailable(): void
    {
        $shop = $this->shop('Unavailable Shop', '422009');
        $this->postalCode('422009', district: 'NASHIK', state: 'MAHARASHTRA');
        $this->postalCode('422011', shippingEnabled: false);
        $service = app(ShopDeliveryServiceabilityService::class);

        $this->shopSetting($shop, 'fulfillment', 'delivery_enabled', false, ShopSetting::TYPE_BOOLEAN);
        $disabled = $service->check($shop, '422009');
        $this->assertFalse($disabled['serviceable']);
        $this->assertSame('delivery_disabled', $disabled['code']);

        $this->shopSetting($shop, 'fulfillment', 'delivery_enabled', true, ShopSetting::TYPE_BOOLEAN);
        $this->assertSame('invalid_pin', $service->check($shop, 'ABC009')['code']);
        $this->assertSame('pin_not_shipping_enabled', $service->check($shop, '999999')['code']);
        $this->assertSame('pin_not_shipping_enabled', $service->check($shop, '422011')['code']);
    }

    public function test_shop_pin_resolution_failure_fails_safely_for_local_only(): void
    {
        $shop = $this->shop('No Master Pin Shop', '123456');
        $this->postalCode('422009', district: 'NASHIK', state: 'MAHARASHTRA');

        $result = app(ShopDeliveryServiceabilityService::class)->check($shop, '422009');

        $this->assertFalse($result['serviceable']);
        $this->assertSame('outside_local_scope', $result['code']);
    }

    public function test_quote_cannot_return_available_when_serviceability_rejects(): void
    {
        $shop = $this->shop('Quote Shop', '422009');
        $this->postalCode('422009', district: 'NASHIK', state: 'MAHARASHTRA');
        $this->postalCode('802301', district: 'BHOJPUR', state: 'BIHAR');

        $quote = app(ShopDeliveryQuoteService::class)->quote($shop, 500, '802301');

        $this->assertFalse($quote['available']);
        $this->assertSame('Delivery is not available to this PIN code.', $quote['reason']);
    }

    public function test_quote_evaluates_minimum_delivery_order_against_shop_subtotal(): void
    {
        $shop = $this->shop('Minimum Quote Shop', '422009');
        $this->postalCode('422009', district: 'NASHIK', state: 'MAHARASHTRA');
        $this->shopSetting($shop, 'fulfillment', 'delivery_min_order_amount', 500, ShopSetting::TYPE_DECIMAL);

        $below = app(ShopDeliveryQuoteService::class)->quote($shop, 499, '422009');
        $equal = app(ShopDeliveryQuoteService::class)->quote($shop, 500, '422009');
        $above = app(ShopDeliveryQuoteService::class)->quote($shop, 501, '422009');

        $this->assertFalse($below['available']);
        $this->assertSame('Minimum delivery order amount is not satisfied.', $below['reason']);
        $this->assertTrue($equal['available']);
        $this->assertTrue($above['available']);
    }

    public function test_quote_treats_zero_or_blank_minimum_as_no_minimum(): void
    {
        $shop = $this->shop('No Minimum Quote Shop', '422009');
        $this->postalCode('422009', district: 'NASHIK', state: 'MAHARASHTRA');
        $service = app(ShopDeliveryQuoteService::class);

        $this->shopSetting($shop, 'fulfillment', 'delivery_min_order_amount', 0, ShopSetting::TYPE_DECIMAL);
        $this->assertTrue($service->quote($shop, 1, '422009')['available']);

        $this->shopSetting($shop, 'fulfillment', 'delivery_min_order_amount', null, ShopSetting::TYPE_DECIMAL);
        $this->assertTrue($service->quote($shop, 1, '422009')['available']);
    }

    public function test_free_delivery_above_uses_shop_subtotal_only(): void
    {
        $shop = $this->shop('Free Threshold Quote Shop', '422009');
        $this->postalCode('422009', district: 'NASHIK', state: 'MAHARASHTRA');
        $this->shopSetting($shop, 'fulfillment', 'delivery_min_order_amount', 500, ShopSetting::TYPE_DECIMAL);
        $this->shopSetting($shop, 'fulfillment', 'delivery_flat_charge', 50, ShopSetting::TYPE_DECIMAL);
        $this->shopSetting($shop, 'fulfillment', 'free_delivery_above', 1500, ShopSetting::TYPE_DECIMAL);
        $service = app(ShopDeliveryQuoteService::class);

        $belowMinimum = $service->quote($shop, 400, '422009');
        $charged = $service->quote($shop, 1000, '422009');
        $free = $service->quote($shop, 1600, '422009');

        $this->assertFalse($belowMinimum['available']);
        $this->assertTrue($charged['available']);
        $this->assertSame(50.0, $charged['charge']);
        $this->assertTrue($free['available']);
        $this->assertSame(0.0, $free['charge']);
    }

    private function shop(string $name, string $postalCode): Shop
    {
        $user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => $name.' Owner',
            'email' => Str::slug($name).'-'.Str::random(8).'@example.test',
            'mobile' => '9'.random_int(100000000, 999999999),
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);
        $merchant = MerchantProfile::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->getKey(),
            'business_name' => $name.' Merchant',
            'verification_status' => 'approved',
            'status' => 'active',
        ]);
        $category = ProductCategory::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => $name.' Category',
            'slug' => Str::slug($name).'-'.Str::random(6),
            'status' => 'active',
        ]);

        return Shop::query()->create([
            'uuid' => (string) Str::uuid(),
            'merchant_id' => $merchant->getKey(),
            'root_product_category_id' => $category->getKey(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(6),
            'address_line_1' => 'Main Road',
            'pincode' => $postalCode,
            'status' => 'active',
        ]);
    }

    private function postalCode(
        string $postalCode,
        bool $shippingEnabled = true,
        string $district = 'NASHIK',
        string $state = 'MAHARASHTRA',
    ): PostalCode {
        return PostalCode::query()->create([
            'source_key' => sha1($postalCode.'|'.strtolower($district).'|'.strtolower($state).'|'.Str::random(8)),
            'circle_name' => $state.' Circle',
            'region_name' => $state.' Region',
            'division_name' => $district.' Division',
            'office_name' => $district.' Test H.O',
            'postal_code' => $postalCode,
            'office_type' => 'HO',
            'delivery_status' => $shippingEnabled ? 'Delivery' : 'Non-Delivery',
            'shipping_enabled' => $shippingEnabled,
            'district' => $district,
            'state' => $state,
            'status' => PostalCode::STATUS_ACTIVE,
        ]);
    }

    private function shopSetting(Shop $shop, string $group, string $key, mixed $value, string $type): void
    {
        app(ShopSettingsService::class)->setTyped((int) $shop->getKey(), $group, $key, $value, $type);
    }
}
