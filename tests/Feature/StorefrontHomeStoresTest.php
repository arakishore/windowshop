<?php

namespace Tests\Feature;

use App\Models\MerchantProfile;
use App\Models\PostalCode;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\Promotion;
use App\Models\PromotionReward;
use App\Models\PromotionTarget;
use App\Models\PromotionTemplate;
use App\Models\Shop;
use App\Models\User;
use App\Services\Storefront\CustomerLocationService;
use Database\Seeders\MasterData\PromotionTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PDO;
use Tests\TestCase;

class StorefrontHomeStoresTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase()
    {
        $pdo = DB::connection()->getPdo();

        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $pdo->sqliteCreateCollation('utf8mb4_unicode_ci', fn (string $left, string $right): int => strcmp($left, $right));
        }
    }

    public function test_homepage_renders_only_active_public_stores_and_uses_existing_location_scope(): void
    {
        $this->postalCode('111111', 'Alpha');
        $this->postalCode('222222', 'Beta');
        $nearby = $this->shop('Nearby Public Store', pincode: '111111');
        $this->shop('Other District Store', pincode: '222222');
        $this->shop('Inactive Store', pincode: '111111', status: 'inactive');
        $this->shop('Inactive Merchant Store', pincode: '111111', merchantStatus: 'inactive');

        $response = $this->withSession([CustomerLocationService::SESSION_KEY => '111111'])
            ->get(route('storefront.home'))
            ->assertOk();
        $section = $this->storesSection($response->getContent());

        $this->assertStringContainsString('Stores Near You', $section);
        $this->assertStringContainsString('Explore local stores near Alpha', $section);
        $this->assertStringContainsString($nearby->name, $section);
        $this->assertStringContainsString(route('storefront.stores.show', $nearby->slug), $section);
        $this->assertStringNotContainsString('Other District Store', $section);
        $this->assertStringNotContainsString('Inactive Store', $section);
        $this->assertStringNotContainsString('Inactive Merchant Store', $section);
    }

    public function test_current_offers_rank_first_count_once_and_non_offer_stores_fall_back_to_newest(): void
    {
        $this->seed(PromotionTemplateSeeder::class);
        $activeOfferShop = $this->shop('Active Offer Store', createdAt: now()->subDays(10));
        $newestFallback = $this->shop('Newest Fallback Store', createdAt: now());
        $inactiveOfferShop = $this->shop('Inactive Offer Store', createdAt: now()->subHour());
        $futureOfferShop = $this->shop('Future Offer Store', createdAt: now()->subHours(2));
        $expiredOfferShop = $this->shop('Expired Offer Store', createdAt: now()->subHours(3));
        $oldestFallback = $this->shop('Oldest Fallback Store', createdAt: now()->subHours(4));

        $this->promotion($activeOfferShop, 'First Active Offer');
        $this->promotion($activeOfferShop, 'Second Active Offer');
        $this->promotion($inactiveOfferShop, 'Inactive Offer', status: Promotion::STATUS_INACTIVE);
        $this->promotion($futureOfferShop, 'Future Offer', startsAt: now()->addDay());
        $this->promotion($expiredOfferShop, 'Expired Offer', endsAt: now()->subDay());

        $section = $this->storesSection($this->get(route('storefront.home'))->assertOk()->getContent());

        $this->assertLessThan(strpos($section, $newestFallback->name), strpos($section, $activeOfferShop->name));
        $this->assertLessThan(strpos($section, $inactiveOfferShop->name), strpos($section, $newestFallback->name));
        $this->assertLessThan(strpos($section, $futureOfferShop->name), strpos($section, $inactiveOfferShop->name));
        $this->assertLessThan(strpos($section, $expiredOfferShop->name), strpos($section, $futureOfferShop->name));
        $this->assertLessThan(strpos($section, $oldestFallback->name), strpos($section, $expiredOfferShop->name));
        $this->assertStringContainsString('shop-offers-badge', $section);
        $this->assertStringContainsString('SPECIAL', $section);
        $this->assertStringContainsString('OFFERS', $section);
        $this->assertStringContainsString('2 offers available', $section);
        $this->assertSame(6, substr_count($section, '<div class="home-stores__item"'));
        $this->assertStringNotContainsString('0 Offers', $section);
    }

    public function test_homepage_limits_stores_to_six_before_rendering(): void
    {
        for ($index = 1; $index <= 8; $index++) {
            $this->shop('Limit Store '.$index, createdAt: now()->subMinutes(8 - $index));
        }

        $section = $this->storesSection($this->get(route('storefront.home'))->assertOk()->getContent());

        $this->assertSame(6, substr_count($section, '<div class="home-stores__item"'));
        $this->assertStringContainsString('Limit Store 8', $section);
        $this->assertStringContainsString('Limit Store 3', $section);
        $this->assertStringNotContainsString('Limit Store 2', $section);
        $this->assertStringNotContainsString('Limit Store 1', $section);
    }

    public function test_homepage_offers_shows_one_current_artwork_per_shop_for_at_most_three_nearby_shops(): void
    {
        $this->seed(PromotionTemplateSeeder::class);
        $this->postalCode('111111', 'Alpha');
        $this->postalCode('222222', 'Beta');
        $oldestShop = $this->shop('Oldest Artwork Shop', pincode: '111111', createdAt: now()->subDays(4));
        $secondShop = $this->shop('Second Artwork Shop', pincode: '111111', createdAt: now()->subDays(3));
        $thirdShop = $this->shop('Third Artwork Shop', pincode: '111111', createdAt: now()->subDays(2));
        $newestShop = $this->shop('Newest Artwork Shop', pincode: '111111', createdAt: now()->subDay());
        $otherShop = $this->shop('Other Offer Shop', pincode: '222222');
        $this->promotion($oldestShop, 'Oldest Artwork Offer', imagePath: 'promotions/oldest/web.webp');
        $second = $this->promotion($secondShop, 'Second Artwork Offer', imagePath: 'promotions/second/web.webp');
        $third = $this->promotion($thirdShop, 'Third Artwork Offer', imagePath: 'promotions/third/web.webp');
        $newest = $this->promotion($newestShop, 'Newest Artwork Offer', imagePath: 'promotions/newest/web.webp');
        $newest->forceFill(['priority' => 10])->save();
        $this->promotion($newestShop, 'Second Newest Shop Offer', imagePath: 'promotions/newest/second.webp');
        $this->promotion($newestShop, 'Inactive Artwork', status: Promotion::STATUS_INACTIVE, imagePath: 'promotions/invalid/inactive.webp');
        $this->promotion($newestShop, 'Future Artwork', startsAt: now()->addDay(), imagePath: 'promotions/invalid/future.webp');
        $this->promotion($newestShop, 'Expired Artwork', endsAt: now()->subDay(), imagePath: 'promotions/invalid/expired.webp');
        $this->promotion($otherShop, 'Other District Artwork', imagePath: 'promotions/other/web.webp');

        $response = $this->withSession([CustomerLocationService::SESSION_KEY => '111111'])
            ->get(route('storefront.home'))
            ->assertOk();
        $section = $this->offersSection($response->getContent());

        $this->assertStringContainsString('Offers Near You', $section);
        $this->assertStringContainsString('Discover offers from local stores near you.', $section);
        $this->assertSame(3, substr_count($section, 'class="home-offer"'));
        $this->assertSame(3, substr_count($section, 'class="home-offer__artwork"'));
        $this->assertStringContainsString('storage/promotions/newest/web.webp', $section);
        $this->assertStringNotContainsString('storage/promotions/newest/second.webp', $section);
        $this->assertStringContainsString('storage/promotions/third/web.webp', $section);
        $this->assertStringContainsString('storage/promotions/second/web.webp', $section);
        $this->assertStringNotContainsString('storage/promotions/oldest/web.webp', $section);
        $this->assertStringNotContainsString('storage/promotions/invalid/inactive.webp', $section);
        $this->assertStringNotContainsString('storage/promotions/invalid/future.webp', $section);
        $this->assertStringNotContainsString('storage/promotions/invalid/expired.webp', $section);
        $this->assertStringNotContainsString('storage/promotions/other/web.webp', $section);
        $this->assertStringContainsString(route('storefront.stores.show', $newestShop->slug), $section);
        $this->assertStringContainsString(route('storefront.stores.offers', ['slug' => $newestShop->slug, 'promotion' => $newest->uuid]), str_replace('&amp;', '&', $section));
        $this->assertStringContainsString(route('storefront.stores.offers', ['slug' => $thirdShop->slug, 'promotion' => $third->uuid]), str_replace('&amp;', '&', $section));
        $this->assertStringContainsString(route('storefront.stores.offers', ['slug' => $secondShop->slug, 'promotion' => $second->uuid]), str_replace('&amp;', '&', $section));
        $this->assertLessThan(strpos($section, $thirdShop->name), strpos($section, $newestShop->name));
        $this->assertLessThan(strpos($section, $secondShop->name), strpos($section, $thirdShop->name));
        $this->assertStringNotContainsString('shop-profile-offer-card', $section);
        $this->assertStringNotContainsString('shop-profile-offer-carousel', $section);

        $content = $response->getContent();
        $this->assertStringNotContainsString('Elevate Your', $content);
        $this->assertStringNotContainsString('Hurry! Deals On', $content);
        $this->assertStringNotContainsString('BIG SEASON SALE', $content);
        $this->assertStringNotContainsString('Nature’s Support', $content);
        $this->assertStringNotContainsString('id="top-picks"', $content);
    }

    public function test_homepage_hides_offers_section_when_no_nearby_shop_has_current_offers(): void
    {
        $this->seed(PromotionTemplateSeeder::class);
        $shop = $this->shop('No Current Offers Shop');
        $this->promotion($shop, 'Old Expired Offer', endsAt: now()->subDay());
        $this->promotion($shop, 'Current Offer Without Artwork');

        $content = $this->get(route('storefront.home'))->assertOk()->getContent();

        $this->assertStringNotContainsString('id="offers-near-you"', $content);
        $this->assertStringContainsString('id="stores-near-you"', $content);
        $this->assertStringContainsString('Shop By Categories', $content);
    }

    public function test_homepage_offers_renders_only_qualifying_artwork_without_placeholders(): void
    {
        $this->seed(PromotionTemplateSeeder::class);
        $firstShop = $this->shop('First Artwork Shop');
        $secondShop = $this->shop('Second Artwork Shop');
        $noArtworkShop = $this->shop('Card Only Offer Shop');
        $this->promotion($firstShop, 'First Artwork Offer', imagePath: 'promotions/first/web.webp');
        $this->promotion($secondShop, 'Second Artwork Offer', imagePath: 'promotions/second/web.webp');
        $this->promotion($noArtworkShop, 'Card Only Active Offer');

        $section = $this->offersSection($this->get(route('storefront.home'))->assertOk()->getContent());

        $this->assertSame(2, substr_count($section, 'class="home-offer"'));
        $this->assertStringContainsString($firstShop->name, $section);
        $this->assertStringContainsString($secondShop->name, $section);
        $this->assertStringNotContainsString($noArtworkShop->name, $section);
        $this->assertStringNotContainsString('placeholder', $section);
    }

    public function test_store_page_keeps_the_shared_offer_carousel_and_links(): void
    {
        $this->seed(PromotionTemplateSeeder::class);
        $shop = $this->shop('Shared Carousel Shop');
        $promotion = $this->promotion($shop, 'Shared Carousel Offer');

        $response = $this->get(route('storefront.stores.show', $shop->slug))->assertOk();

        $response
            ->assertSee('Offers from '.$shop->name)
            ->assertSee('Shared Carousel Offer')
            ->assertSee('shop-profile-offer-carousel', false)
            ->assertSee(route('storefront.stores.offers', $shop->slug), false)
            ->assertSee(route('storefront.stores.offers', [
                'slug' => $shop->slug,
                'promotion' => $promotion->uuid,
            ]), false);
    }

    public function test_homepage_new_arrivals_uses_newest_eligible_storefront_products(): void
    {
        $this->postalCode('111111', 'Alpha');
        $this->postalCode('222222', 'Beta');
        $nearbyShop = $this->shop('New Arrival Shop', pincode: '111111');
        $otherShop = $this->shop('Other Location Shop', pincode: '222222');

        for ($index = 1; $index <= 9; $index++) {
            $this->product($nearbyShop, 'Nearby Product '.$index, createdAt: now()->subMinutes(10 - $index));
        }

        $this->product($nearbyShop, 'Inactive Nearby Product', status: 'inactive', createdAt: now());
        $this->product($nearbyShop, 'Unavailable Nearby Product', sellable: false, createdAt: now());
        $this->product($otherShop, 'Other Location Product', createdAt: now());

        $content = $this->withSession([CustomerLocationService::SESSION_KEY => '111111'])
            ->get(route('storefront.home'))
            ->assertOk()
            ->getContent();
        $start = strpos($content, 'id="top-picks"');
        $this->assertNotFalse($start);
        $sectionEnd = strpos($content, '</section>', $start);
        $this->assertNotFalse($sectionEnd);
        $section = substr($content, $start, $sectionEnd + strlen('</section>') - $start);

        $this->assertStringContainsString('Fresh products recently added by local stores.', $section);
        $this->assertStringNotContainsString('Shop by Store', $content);
        $this->assertStringContainsString(route('storefront.products'), $section);
        $this->assertSame(8, substr_count($section, 'class="card-product '));
        $this->assertStringNotContainsString('Nearby Product 1', $section);
        $this->assertStringNotContainsString('Inactive Nearby Product', $section);
        $this->assertStringNotContainsString('Unavailable Nearby Product', $section);
        $this->assertStringNotContainsString('Other Location Product', $section);
        $this->assertStringContainsString($nearbyShop->name, $section);
        $this->assertStringContainsString(route('storefront.stores.show', $nearbyShop->slug), $section);
        $this->assertStringContainsString('₹90.00', $section);
        $this->assertStringNotContainsString('class="compare"', $section);
        $this->assertStringNotContainsString('Quick Add', $section);
        $this->assertLessThan(strpos($section, 'Nearby Product 8'), strpos($section, 'Nearby Product 9'));
        $this->assertLessThan($start, strpos($content, 'id="offers-near-you"'));
    }

    private function shop(
        string $name,
        ?string $pincode = null,
        string $status = 'active',
        string $merchantStatus = 'active',
        $createdAt = null,
    ): Shop {
        $user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => $name.' Owner',
            'email' => Str::slug($name).'-'.Str::random(5).'@example.test',
            'mobile' => '9'.random_int(100000000, 999999999),
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);
        $merchant = MerchantProfile::query()->create([
            'user_id' => $user->getKey(),
            'business_name' => $name,
            'verification_status' => 'approved',
            'status' => $merchantStatus,
        ]);
        $category = ProductCategory::query()->create([
            'name' => $name.' Category',
            'slug' => Str::slug($name).'-category-'.Str::random(5),
            'status' => 'active',
        ]);

        $shop = Shop::query()->create([
            'merchant_id' => $merchant->getKey(),
            'root_product_category_id' => $category->getKey(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(5),
            'address_line_1' => 'Main Road',
            'pincode' => $pincode,
            'status' => $status,
        ]);

        if ($createdAt !== null) {
            $shop->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();
        }

        return $shop;
    }

    private function promotion(
        Shop $shop,
        string $name,
        string $status = Promotion::STATUS_ACTIVE,
        $startsAt = null,
        $endsAt = null,
        ?string $imagePath = null,
    ): Promotion {
        $template = PromotionTemplate::query()->where('code', 'fixed_discount')->firstOrFail();
        $promotion = Promotion::query()->create([
            'merchant_id' => $shop->merchant_id,
            'shop_id' => $shop->getKey(),
            'promotion_template_id' => $template->getKey(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(5),
            'status' => $status,
            'activation_type' => Promotion::ACTIVATION_AUTOMATIC,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'promotional_image_path' => $imagePath,
            'refund_policy_mode' => Promotion::POLICY_INHERIT,
            'exchange_policy_mode' => Promotion::POLICY_INHERIT,
        ]);
        $promotion->rewards()->create([
            'reward_type' => PromotionReward::TYPE_FIXED_DISCOUNT,
            'value_amount' => 100,
        ]);
        $promotion->targets()->create([
            'target_role' => PromotionTarget::ROLE_ELIGIBLE,
            'target_type' => PromotionTarget::TYPE_ALL,
            'sort_order' => 10,
        ]);

        return $promotion;
    }

    private function product(
        Shop $shop,
        string $name,
        string $status = 'active',
        bool $sellable = true,
        $createdAt = null,
    ): Product {
        $product = Product::query()->create([
            'merchant_id' => $shop->merchant_id,
            'shop_id' => $shop->getKey(),
            'root_product_category_id' => $shop->root_product_category_id,
            'product_category_id' => $shop->root_product_category_id,
            'product_name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(5),
            'status' => $status,
            'tax_mode' => 'inherit',
        ]);

        ProductVariant::query()->create([
            'product_id' => $product->getKey(),
            'shop_id' => $shop->getKey(),
            'name' => $name,
            'mrp' => '100.00',
            'selling_price' => '90.00',
            'stock_quantity' => 5,
            'low_stock_threshold' => 0,
            'is_sellable' => $sellable,
            'is_default' => true,
            'sort_order' => 0,
            'status' => 'active',
        ]);

        if ($createdAt !== null) {
            $product->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();
        }

        return $product;
    }

    private function postalCode(string $code, string $district): PostalCode
    {
        return PostalCode::query()->create([
            'source_key' => sha1($code.$district),
            'office_name' => $district.' Central',
            'postal_code' => $code,
            'shipping_enabled' => true,
            'district' => $district,
            'state' => 'Test State',
            'status' => PostalCode::STATUS_ACTIVE,
        ]);
    }

    private function storesSection(string $content): string
    {
        $start = strpos($content, '<section id="stores-near-you"');
        $this->assertIsInt($start);
        $end = strpos($content, '</section>', $start);
        $this->assertIsInt($end);

        return substr($content, $start, $end - $start);
    }

    private function offersSection(string $content): string
    {
        $start = strpos($content, '<section id="offers-near-you"');
        $this->assertIsInt($start);
        $end = strpos($content, '</section>', $start);
        $this->assertIsInt($end);

        return substr($content, $start, $end - $start);
    }
}
