<?php

namespace Tests\Feature;

use App\Models\AdminSetting;
use App\Models\MerchantProfile;
use App\Models\PostalCode;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\Shop;
use App\Models\User;
use App\Services\Storefront\CustomerLocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PDO;
use Tests\TestCase;

class StorefrontCustomerLocationTest extends TestCase
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

    protected function setUp(): void
    {
        parent::setUp();

        $this->currencySetting('symbol', 'INR ');
        $this->currencySetting('decimal_places', '2', AdminSetting::TYPE_INTEGER);
        $this->currencySetting('thousands_separator', ',');
        $this->currencySetting('decimal_separator', '.');
        $this->currencySetting('symbol_position', 'before');
    }

    public function test_valid_known_pin_can_be_saved_to_session_and_cookie(): void
    {
        $this->postalCode('422009');

        $response = $this
            ->from(route('storefront.home'))
            ->post(route('storefront.location.postal-code.store'), [
                'postal_code' => '422009',
            ]);

        $response
            ->assertRedirect(route('storefront.home'))
            ->assertSessionHas(CustomerLocationService::SESSION_KEY, '422009')
            ->assertCookie(CustomerLocationService::COOKIE_NAME);
    }

    public function test_invalid_and_non_six_digit_pins_are_rejected(): void
    {
        $this->postalCode('422009');

        $this->postJson(route('storefront.location.postal-code.store'), [
            'postal_code' => 'ABC009',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('postal_code');

        $this->postJson(route('storefront.location.postal-code.store'), [
            'postal_code' => '4220',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('postal_code');

        $this->postJson(route('storefront.location.postal-code.store'), [
            'postal_code' => '42200999',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('postal_code');
    }

    public function test_unknown_pin_is_rejected_against_postal_code_master(): void
    {
        $this->postJson(route('storefront.location.postal-code.store'), [
            'postal_code' => '422009',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('postal_code')
            ->assertJsonFragment([
                'postal_code' => ["We couldn't find this PIN code. Please check and try again."],
            ]);
    }

    public function test_existing_pin_can_be_changed(): void
    {
        $this->postalCode('422009');
        $this->postalCode('422402');

        $this
            ->withSession([CustomerLocationService::SESSION_KEY => '422009'])
            ->postJson(route('storefront.location.postal-code.store'), [
                'postal_code' => '422402',
            ])
            ->assertOk()
            ->assertJsonPath('postal_code', '422402')
            ->assertSessionHas(CustomerLocationService::SESSION_KEY, '422402')
            ->assertCookie(CustomerLocationService::COOKIE_NAME);
    }

    public function test_storefront_can_read_current_pin_from_session_or_cookie(): void
    {
        $this->withSession([CustomerLocationService::SESSION_KEY => '422009'])
            ->get(route('storefront.home'))
            ->assertOk()
            ->assertSee('Shopping near 422009')
            ->assertSee('data-auto-open="0"', false);

        $this->flushSession();

        $this->withCookie(CustomerLocationService::COOKIE_NAME, '422402')
            ->get(route('storefront.home'))
            ->assertOk()
            ->assertSee('Shopping near 422402')
            ->assertSee('data-auto-open="0"', false);
    }

    public function test_guest_without_pin_can_access_storefront_and_modal_is_marked_for_auto_open(): void
    {
        $this->get(route('storefront.home'))
            ->assertOk()
            ->assertSee('Choose location')
            ->assertSee('id="customer-location-modal"', false)
            ->assertSee('data-auto-open="1"', false);
    }

    public function test_pin_selection_does_not_filter_or_block_distant_shop_products(): void
    {
        $fixture = $this->fixture();
        $nearProduct = $this->product($fixture, 'Near Shop Product');
        $this->variant($nearProduct);

        $farShop = $this->shop($fixture['merchant'], $fixture['root'], 'Far Shop', '422402');
        $farProduct = $this->product([...$fixture, 'shop' => $farShop], 'Far Shop Product');
        $this->variant($farProduct);

        $this->withSession([CustomerLocationService::SESSION_KEY => '422009'])
            ->get(route('storefront.products'))
            ->assertOk()
            ->assertSee('Near Shop Product')
            ->assertSee('Far Shop Product');
    }

    public function test_product_delivery_check_can_return_json_without_redirecting(): void
    {
        $this->postalCode('422009', officeName: 'Cidco Colony S.O');
        $fixture = $this->fixture();
        $product = $this->product($fixture, 'Delivery Check Product');
        $this->variant($product);

        $this->postJson(route('storefront.product.delivery-check', $product->slug), [
            'postal_code' => '422009',
        ])
            ->assertOk()
            ->assertJsonPath('status', 'available')
            ->assertJsonPath('postal_code', '422009')
            ->assertJsonFragment([
                'product_slug' => $product->slug,
            ])
            ->assertSessionHas(CustomerLocationService::SESSION_KEY, '422009')
            ->assertCookie(CustomerLocationService::COOKIE_NAME);
    }

    public function test_product_delivery_check_json_validation_errors_do_not_redirect(): void
    {
        $fixture = $this->fixture();
        $product = $this->product($fixture, 'Invalid Delivery Check Product');
        $this->variant($product);

        $this->postJson(route('storefront.product.delivery-check', $product->slug), [
            'postal_code' => '4220',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('status', 'error')
            ->assertJsonValidationErrors('postal_code');
    }

    public function test_valid_latitude_longitude_detects_nearest_postal_code_without_storing_it(): void
    {
        $this->postalCode('422009', latitude: '19.9975000', longitude: '73.7898000', officeName: 'Cidco Colony S.O');
        $this->postalCode('422402', latitude: '19.7167000', longitude: '73.6333000', officeName: 'Ghoti S.O');

        $this->postJson(route('storefront.location.detect'), [
            'latitude' => 19.998,
            'longitude' => 73.79,
            'accuracy' => 35,
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('postal_code', '422009')
            ->assertJsonPath('locality', 'Cidco Colony S.O')
            ->assertJsonPath('district', 'NASHIK')
            ->assertJsonMissingPath('errors')
            ->assertSessionMissing(CustomerLocationService::SESSION_KEY)
            ->assertCookieMissing(CustomerLocationService::COOKIE_NAME);
    }

    public function test_invalid_latitude_longitude_is_rejected(): void
    {
        $this->postJson(route('storefront.location.detect'), [
            'latitude' => 91,
            'longitude' => 73.79,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('latitude');

        $this->postJson(route('storefront.location.detect'), [
            'latitude' => 19.99,
            'longitude' => -181,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('longitude');
    }

    public function test_coordinate_detection_rejects_unreasonable_nearest_match(): void
    {
        $this->postalCode('422009', latitude: '19.9975000', longitude: '73.7898000');

        $this->postJson(route('storefront.location.detect'), [
            'latitude' => 28.6139,
            'longitude' => 77.2090,
            'accuracy' => 50,
        ])
            ->assertNotFound()
            ->assertJsonFragment([
                'message' => "We couldn't match your location to a nearby PIN code. Please enter your PIN code instead.",
            ]);
    }

    public function test_location_service_retrieves_selected_pin_and_postal_code_record(): void
    {
        $postalCode = $this->postalCode('422009', officeName: 'Cidco Colony S.O');
        $service = app(CustomerLocationService::class);
        $request = \Illuminate\Http\Request::create('/');
        $request->setLaravelSession($this->app['session.store']);

        $request->session()->put(CustomerLocationService::SESSION_KEY, '422009');

        $this->assertSame('422009', $service->postalCode($request));
        $this->assertTrue($postalCode->is($service->postalCodeRecord('422009')));
        $this->assertNull($service->postalCodeRecord('ABC009'));
    }

    private function postalCode(
        string $postalCode,
        string $status = PostalCode::STATUS_ACTIVE,
        string $latitude = '19.9975000',
        string $longitude = '73.7898000',
        string $officeName = 'WindowShop Test H.O',
    ): PostalCode
    {
        return PostalCode::query()->create([
            'source_key' => sha1($postalCode.'|'.strtolower($officeName).'|ho|nashik|maharashtra'),
            'circle_name' => 'Maharashtra Circle',
            'region_name' => 'Mumbai Region',
            'division_name' => 'Nashik Division',
            'office_name' => $officeName,
            'postal_code' => $postalCode,
            'office_type' => 'HO',
            'delivery_status' => 'Delivery',
            'shipping_enabled' => true,
            'district' => 'NASHIK',
            'state' => 'MAHARASHTRA',
            'latitude' => $latitude,
            'longitude' => $longitude,
            'status' => $status,
        ]);
    }

    /**
     * @return array{merchant: MerchantProfile, shop: Shop, root: ProductCategory, category: ProductCategory}
     */
    private function fixture(): array
    {
        $merchant = $this->merchant('Location Merchant');
        $root = ProductCategory::query()->create([
            'name' => 'Location Root '.Str::random(4),
            'slug' => 'location-root-'.Str::random(8),
            'status' => 'active',
        ]);
        $category = ProductCategory::query()->create([
            'parent_id' => $root->getKey(),
            'name' => 'Location Leaf '.Str::random(4),
            'slug' => 'location-leaf-'.Str::random(8),
            'status' => 'active',
        ]);
        $shop = $this->shop($merchant, $root, 'Near Shop', '422009');

        return compact('merchant', 'shop', 'root', 'category');
    }

    private function merchant(string $name): MerchantProfile
    {
        $user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => $name.' Owner',
            'email' => Str::slug($name).'-'.Str::random(6).'@example.test',
            'mobile' => '9'.random_int(100000000, 999999999),
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);

        return MerchantProfile::query()->create([
            'user_id' => $user->getKey(),
            'business_name' => $name,
            'verification_status' => 'approved',
            'status' => 'active',
        ]);
    }

    private function shop(MerchantProfile $merchant, ProductCategory $root, string $name, string $postalCode): Shop
    {
        return Shop::query()->create([
            'merchant_id' => $merchant->getKey(),
            'root_product_category_id' => $root->getKey(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(6),
            'address_line_1' => 'Main Road',
            'pincode' => $postalCode,
            'status' => 'active',
        ]);
    }

    /**
     * @param array{merchant: MerchantProfile, shop: Shop, root: ProductCategory, category: ProductCategory} $fixture
     */
    private function product(array $fixture, string $name): Product
    {
        return Product::query()->create([
            'merchant_id' => $fixture['merchant']->getKey(),
            'shop_id' => $fixture['shop']->getKey(),
            'root_product_category_id' => $fixture['root']->getKey(),
            'product_category_id' => $fixture['category']->getKey(),
            'product_name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(6),
            'status' => 'active',
            'tax_mode' => 'inherit',
        ]);
    }

    private function variant(Product $product): ProductVariant
    {
        return ProductVariant::query()->create([
            'product_id' => $product->getKey(),
            'shop_id' => $product->shop_id,
            'name' => $product->product_name,
            'mrp' => '100.00',
            'selling_price' => '90.00',
            'stock_quantity' => 5,
            'low_stock_threshold' => 0,
            'is_sellable' => true,
            'is_default' => true,
            'sort_order' => 0,
            'status' => 'active',
        ]);
    }

    private function currencySetting(string $key, string $value, string $type = AdminSetting::TYPE_STRING): void
    {
        AdminSetting::query()->updateOrCreate(
            ['group' => 'currency', 'setting_key' => $key],
            ['setting_value' => $value, 'setting_type' => $type],
        );
    }
}
