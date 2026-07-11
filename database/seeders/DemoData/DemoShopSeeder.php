<?php

namespace Database\Seeders\DemoData;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class DemoShopSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = now();

        DB::transaction(function () use ($now): void {
            $location = $this->defaultLocation();

            foreach ($this->shops() as $shop) {
                $merchantId = DB::table('merchant_profiles')
                    ->join('users', 'users.id', '=', 'merchant_profiles.user_id')
                    ->where('users.email', $shop['merchant_email'])
                    ->value('merchant_profiles.id');

                if ($merchantId === null) {
                    throw new RuntimeException("Demo merchant profile not found for {$shop['merchant_email']}.");
                }

                $categoryId = DB::table('shop_categories')
                    ->where('slug', $shop['category_slug'])
                    ->where('status', 'active')
                    ->whereNull('deleted_at')
                    ->value('id');

                if ($categoryId === null) {
                    throw new RuntimeException("Active shop category not found for {$shop['category_slug']}.");
                }

                $exists = DB::table('shops')
                    ->where('slug', $shop['slug'])
                    ->exists();

                DB::table('shops')->updateOrInsert(
                    ['slug' => $shop['slug']],
                    array_merge([
                        'merchant_id' => $merchantId,
                        'shop_category_id' => $categoryId,
                        'name' => $shop['name'],
                        'short_description' => $shop['short_description'],
                        'description' => $shop['description'],
                        'logo_path' => null,
                        'banner_path' => null,
                        'email' => $shop['email'],
                        'mobile' => $shop['mobile'],
                        'whatsapp_number' => $shop['whatsapp_number'],
                        'website_url' => $shop['website_url'],
                        'address_line_1' => $shop['address_line_1'],
                        'address_line_2' => $shop['address_line_2'],
                        'landmark' => $shop['landmark'],
                        'country_id' => $location['country_id'],
                        'state_id' => $location['state_id'],
                        'city_id' => $location['city_id'],
                        'pincode' => $shop['pincode'],
                        'latitude' => $shop['latitude'],
                        'longitude' => $shop['longitude'],
                        'status' => $shop['status'],
                        'admin_note' => $shop['admin_note'],
                        'deleted_at' => null,
                        'updated_at' => $now,
                    ], $exists ? [] : [
                        'uuid' => (string) Str::uuid(),
                        'created_at' => $now,
                    ]),
                );

                $shopId = DB::table('shops')
                    ->where('slug', $shop['slug'])
                    ->value('id');

                foreach ($shop['audience_slugs'] as $audienceSlug) {
                    $audienceId = DB::table('shop_audiences')
                        ->where('slug', $audienceSlug)
                        ->where('status', 'active')
                        ->whereNull('deleted_at')
                        ->value('id');

                    if ($audienceId === null) {
                        throw new RuntimeException("Active shop audience not found for {$audienceSlug}.");
                    }

                    DB::table('shop_audience_map')->updateOrInsert(
                        [
                            'shop_id' => $shopId,
                            'audience_id' => $audienceId,
                        ],
                        fn (bool $exists) => [
                            'updated_at' => $now,
                            ...($exists ? [] : ['created_at' => $now]),
                        ],
                    );
                }
            }
        });
    }

    /**
     * @return array{country_id: int, state_id: int, city_id: int}
     */
    private function defaultLocation(): array
    {
        $countryId = DB::table('loc_countries')
            ->where('iso2', 'IN')
            ->whereNull('deleted_at')
            ->value('id');

        $stateId = DB::table('loc_states')
            ->where('country_id', $countryId)
            ->where('iso2', 'MH')
            ->whereNull('deleted_at')
            ->value('id');

        $cityId = DB::table('loc_cities')
            ->where('country_id', $countryId)
            ->where('state_id', $stateId)
            ->where('name', 'Nashik')
            ->whereNull('deleted_at')
            ->value('id');

        if ($countryId === null || $stateId === null || $cityId === null) {
            throw new RuntimeException('India, Maharashtra and Nashik must exist before seeding demo shops.');
        }

        return [
            'country_id' => $countryId,
            'state_id' => $stateId,
            'city_id' => $cityId,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function shops(): array
    {
        return [
            [
                'merchant_email' => 'priya@vanawomen.test',
                'category_slug' => 'apparel',
                'audience_slugs' => ['women'],
                'name' => "Vana Women's Studio - Main Branch",
                'slug' => 'vana-womens-studio-main-branch',
                'short_description' => 'Curated ethnic and occasion wear for women.',
                'description' => 'A demo boutique storefront featuring sarees, kurtas and everyday womenswear.',
                'email' => 'hello@vanawomen.test',
                'mobile' => '9876543210',
                'whatsapp_number' => '9876543210',
                'website_url' => null,
                'address_line_1' => 'Shop 12, College Road',
                'address_line_2' => 'Near Big Bazaar',
                'landmark' => 'Canada Corner',
                'pincode' => '422005',
                'latitude' => '20.0047000',
                'longitude' => '73.7796000',
                'status' => 'active',
                'admin_note' => 'Primary demo shop for women apparel workflows.',
            ],
            [
                'merchant_email' => 'priya@vanawomen.test',
                'category_slug' => 'jewellery-accessories',
                'audience_slugs' => ['women'],
                'name' => 'Vana Accessories Corner',
                'slug' => 'vana-accessories-corner',
                'short_description' => 'Fashion jewellery and accessories.',
                'description' => 'A compact accessory-focused demo shop linked to the Vana merchant account.',
                'email' => 'accessories@vanawomen.test',
                'mobile' => '9876543211',
                'whatsapp_number' => '9876543211',
                'website_url' => null,
                'address_line_1' => 'Shop 12A, College Road',
                'address_line_2' => 'Near Big Bazaar',
                'landmark' => 'Canada Corner',
                'pincode' => '422005',
                'latitude' => '20.0049000',
                'longitude' => '73.7798000',
                'status' => 'inactive',
                'admin_note' => 'Used to demonstrate a merchant with multiple shops.',
            ],
            [
                'merchant_email' => 'neha@gracebloom.test',
                'category_slug' => 'beauty-cosmetics',
                'audience_slugs' => ['women', 'unisex'],
                'name' => 'Grace & Bloom Beauty',
                'slug' => 'grace-bloom-beauty',
                'short_description' => 'Beauty, cosmetics and self-care essentials.',
                'description' => 'A demo cosmetics storefront for catalogue and shop verification screens.',
                'email' => 'shop@gracebloom.test',
                'mobile' => '9876501234',
                'whatsapp_number' => '9876501234',
                'website_url' => null,
                'address_line_1' => 'Unit 4, Fashion Street',
                'address_line_2' => 'MG Road',
                'landmark' => 'Near CBS Signal',
                'pincode' => '422001',
                'latitude' => '19.9975000',
                'longitude' => '73.7898000',
                'status' => 'active',
                'admin_note' => 'Beauty category demo shop.',
            ],
            [
                'merchant_email' => 'pooja@rangoliethnic.test',
                'category_slug' => 'apparel',
                'audience_slugs' => ['women', 'kids'],
                'name' => 'Rangoli Ethnic Wear',
                'slug' => 'rangoli-ethnic-wear',
                'short_description' => 'Ethnic wear for women and kids.',
                'description' => 'A pending-verification demo shop for merchant onboarding flows.',
                'email' => 'shop@rangoliethnic.test',
                'mobile' => '9876512345',
                'whatsapp_number' => '9876512345',
                'website_url' => null,
                'address_line_1' => 'Plot 18, Gangapur Road',
                'address_line_2' => null,
                'landmark' => 'Near Jehan Circle',
                'pincode' => '422013',
                'latitude' => '20.0115000',
                'longitude' => '73.7349000',
                'status' => 'inactive',
                'admin_note' => 'Pending shop demo data.',
            ],
            [
                'merchant_email' => 'rahul@urbanman.test',
                'category_slug' => 'footwear',
                'audience_slugs' => ['men', 'unisex'],
                'name' => 'Urban Man Footwear',
                'slug' => 'urban-man-footwear',
                'short_description' => 'Casual and formal footwear for men.',
                'description' => 'A demo footwear shop connected to the Urban Man merchant.',
                'email' => 'footwear@urbanman.test',
                'mobile' => '9876523456',
                'whatsapp_number' => '9876523456',
                'website_url' => null,
                'address_line_1' => 'Shop 7, City Centre Mall',
                'address_line_2' => 'Untwadi Road',
                'landmark' => 'Lavate Nagar',
                'pincode' => '422009',
                'latitude' => '19.9859000',
                'longitude' => '73.7637000',
                'status' => 'active',
                'admin_note' => 'Men footwear demo shop.',
            ],
            [
                'merchant_email' => 'amit@classicmenswear.test',
                'category_slug' => 'apparel',
                'audience_slugs' => ['men'],
                'name' => 'Classic Menswear Nashik',
                'slug' => 'classic-menswear-nashik',
                'short_description' => 'Traditional and formal menswear.',
                'description' => 'An inactive demo shop for status filtering and review screens.',
                'email' => 'shop@classicmenswear.test',
                'mobile' => '9876534567',
                'whatsapp_number' => '9876534567',
                'website_url' => null,
                'address_line_1' => 'Ground Floor, Main Road',
                'address_line_2' => 'Shalimar',
                'landmark' => 'Near Old CBS',
                'pincode' => '422001',
                'latitude' => '19.9993000',
                'longitude' => '73.7868000',
                'status' => 'inactive',
                'admin_note' => 'Rejected shop demo data for admin review states.',
            ],
        ];
    }
}
