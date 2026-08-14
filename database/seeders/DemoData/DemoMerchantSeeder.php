<?php

namespace Database\Seeders\DemoData;

use App\Enums\UserRegistrationSource;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\MerchantProfile;
use App\Services\ProductAvailability\MerchantAvailabilityStatusSeeder;
use RuntimeException;

class DemoMerchantSeeder extends Seeder
{
    /**
     * Seed realistic demo merchant accounts for local Merchant CRUD testing.
     */
    public function run(): void
    {
        $now = now();

        DB::transaction(function () use ($now): void {
            $roleId = DB::table('auth_roles')
                ->where('slug', 'merchant')
                ->where('status', 'active')
                ->whereNull('deleted_at')
                ->value('id');

            if ($roleId === null) {
                throw new RuntimeException('The active merchant role must exist before seeding demo merchants.');
            }

            $location = $this->defaultLocation();

            foreach ($this->merchants() as $merchant) {
                $userExists = DB::table('users')
                    ->where('email', $merchant['email'])
                    ->exists();

                DB::table('users')->updateOrInsert(
                    ['email' => $merchant['email']],
                    array_merge([
                        'name' => $merchant['owner_name'],
                        'mobile' => $merchant['mobile'],
                        'password' => Hash::make('password'),
                        'status' => $merchant['status'],
                        'deleted_at' => null,
                        'updated_at' => $now,
                    ], $userExists ? [] : [
                        'uuid' => (string) Str::uuid(),
                        'registration_source' => UserRegistrationSource::ADMIN->value,
                        'created_at' => $now,
                    ]),
                );

                $userId = DB::table('users')
                    ->where('email', $merchant['email'])
                    ->value('id');

                DB::table('auth_user_roles')->updateOrInsert(
                    [
                        'user_id' => $userId,
                        'role_id' => $roleId,
                    ],
                    fn (bool $exists) => [
                        'updated_at' => $now,
                        ...($exists ? [] : ['created_at' => $now]),
                    ],
                );

                $profileExists = DB::table('merchant_profiles')
                    ->where('user_id', $userId)
                    ->exists();

                DB::table('merchant_profiles')->updateOrInsert(
                    ['user_id' => $userId],
                    array_merge([
                        'business_name' => $merchant['business_name'],
                        'legal_name' => $merchant['legal_name'],
                        'business_type' => $merchant['business_type'],
                        'gst_number' => $merchant['gst_number'],
                        'has_shop_license' => $merchant['has_shop_license'],
                        'has_fssai' => $merchant['has_fssai'],
                        'contact_person_name' => $merchant['owner_name'],
                        'contact_email' => $merchant['email'],
                        'contact_mobile' => $merchant['mobile'],
                        'alternate_mobile' => $merchant['alternate_mobile'],
                        'verification_status' => $merchant['verification_status'],
                        'status' => $merchant['status'],
                        'admin_note' => $merchant['admin_note'],
                        'deleted_at' => null,
                        'updated_at' => $now,
                    ], $profileExists ? [] : [
                        'uuid' => (string) Str::uuid(),
                        'created_at' => $now,
                    ]),
                );

                $merchantId = DB::table('merchant_profiles')
                    ->where('user_id', $userId)
                    ->value('id');

                app(MerchantAvailabilityStatusSeeder::class)
                    ->seedDefaultsForMerchant(MerchantProfile::query()->findOrFail($merchantId));

                DB::table('merchant_addresses')->updateOrInsert(
                    [
                        'merchant_id' => $merchantId,
                        'address_type' => 'business',
                    ],
                    fn (bool $exists) => [
                        'address_line_1' => $merchant['address_line_1'],
                        'address_line_2' => $merchant['address_line_2'],
                        'landmark' => $merchant['landmark'],
                        'country_id' => $location['country_id'],
                        'state_id' => $location['state_id'],
                        'city_id' => $location['city_id'],
                        'pincode' => $merchant['pincode'],
                        'status' => 'active',
                        'deleted_at' => null,
                        'updated_at' => $now,
                        ...($exists ? [] : [
                            'uuid' => (string) Str::uuid(),
                            'created_at' => $now,
                        ]),
                    ],
                );
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
            throw new RuntimeException('India, Maharashtra and Nashik must exist before seeding demo merchants.');
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
    private function merchants(): array
    {
        return [
            [
                'business_name' => "Vana Women's Studio",
                'owner_name' => 'Priya Sharma',
                'email' => 'priya@vanawomen.test',
                'mobile' => '9876543210',
                'alternate_mobile' => '9823456710',
                'legal_name' => "Vana Women's Studio",
                'business_type' => 'proprietorship',
                'gst_number' => '27ABCDE1234F1Z5',
                'verification_status' => 'approved',
                'status' => 'active',
                'has_shop_license' => true,
                'has_fssai' => null,
                'admin_note' => 'Approved after successful GST and PAN review.',
                'address_line_1' => 'Shop 12, College Road',
                'address_line_2' => 'Near Big Bazaar',
                'landmark' => 'Canada Corner',
                'pincode' => '422005',
            ],
            [
                'business_name' => 'Grace & Bloom Fashion',
                'owner_name' => 'Neha Patil',
                'email' => 'neha@gracebloom.test',
                'mobile' => '9876501234',
                'alternate_mobile' => '9823401234',
                'legal_name' => 'Grace & Bloom Fashion LLP',
                'business_type' => 'partnership',
                'gst_number' => '27FGHIJ5678K1Z2',
                'verification_status' => 'approved',
                'status' => 'active',
                'has_shop_license' => true,
                'has_fssai' => null,
                'admin_note' => 'Verified successfully after manual profile review.',
                'address_line_1' => 'Unit 4, Fashion Street',
                'address_line_2' => 'MG Road',
                'landmark' => 'Near CBS Signal',
                'pincode' => '422001',
            ],
            [
                'business_name' => 'Rangoli Ethnic Wear',
                'owner_name' => 'Pooja Deshmukh',
                'email' => 'pooja@rangoliethnic.test',
                'mobile' => '9876512345',
                'alternate_mobile' => '9823412345',
                'legal_name' => 'Rangoli Ethnic Wear',
                'business_type' => 'proprietorship',
                'gst_number' => '27KLMNO9012P1Z8',
                'verification_status' => 'pending',
                'status' => 'active',
                'has_shop_license' => null,
                'has_fssai' => null,
                'admin_note' => 'Waiting for admin review of GST information.',
                'address_line_1' => 'Plot 18, Gangapur Road',
                'address_line_2' => null,
                'landmark' => 'Near Jehan Circle',
                'pincode' => '422013',
            ],
            [
                'business_name' => 'Urban Man Clothing',
                'owner_name' => 'Rahul Verma',
                'email' => 'rahul@urbanman.test',
                'mobile' => '9876523456',
                'alternate_mobile' => '9823423456',
                'legal_name' => 'Urban Man Clothing Private Limited',
                'business_type' => 'pvt_ltd',
                'gst_number' => '27PQRST3456U1Z9',
                'verification_status' => 'submitted',
                'status' => 'active',
                'has_shop_license' => true,
                'has_fssai' => null,
                'admin_note' => 'Profile details submitted and awaiting admin review.',
                'address_line_1' => 'Shop 7, City Centre Mall',
                'address_line_2' => 'Untwadi Road',
                'landmark' => 'Lavate Nagar',
                'pincode' => '422009',
            ],
            [
                'business_name' => 'Classic Menswear Nashik',
                'owner_name' => 'Amit Kulkarni',
                'email' => 'amit@classicmenswear.test',
                'mobile' => '9876534567',
                'alternate_mobile' => '9823434567',
                'legal_name' => 'Classic Menswear Nashik',
                'business_type' => 'partnership',
                'gst_number' => '27UVWXY7890Z1Z4',
                'verification_status' => 'rejected',
                'status' => 'inactive',
                'has_shop_license' => false,
                'has_fssai' => null,
                'admin_note' => 'Business profile details need admin review.',
                'address_line_1' => 'Ground Floor, Main Road',
                'address_line_2' => 'Shalimar',
                'landmark' => 'Near Old CBS',
                'pincode' => '422001',
            ],
            [
                'business_name' => 'TechNest Electronics',
                'owner_name' => 'Sagar Joshi',
                'email' => 'sagar@technest.test',
                'mobile' => '9876545678',
                'alternate_mobile' => '9823445678',
                'legal_name' => 'TechNest Electronics',
                'business_type' => 'proprietorship',
                'gst_number' => '27TECHN1234E1Z7',
                'verification_status' => 'approved',
                'status' => 'active',
                'has_shop_license' => true,
                'has_fssai' => null,
                'admin_note' => 'Electronics demo merchant for mobile and accessory workflows.',
                'address_line_1' => 'Shop 3, College Road',
                'address_line_2' => 'Opposite Electronics Market',
                'landmark' => 'Canada Corner',
                'pincode' => '422005',
            ],
            [
                'business_name' => 'Daily Basket Grocery',
                'owner_name' => 'Kiran Shah',
                'email' => 'kiran@dailybasket.test',
                'mobile' => '9876556789',
                'alternate_mobile' => '9823456789',
                'legal_name' => 'Daily Basket Grocery',
                'business_type' => 'proprietorship',
                'gst_number' => '27GROC1234R1Z1',
                'verification_status' => 'approved',
                'status' => 'active',
                'has_shop_license' => true,
                'has_fssai' => true,
                'admin_note' => 'Grocery demo merchant with FSSAI enabled.',
                'address_line_1' => 'Shop 5, Indira Nagar',
                'address_line_2' => 'Near Market Yard',
                'landmark' => 'Jogging Track',
                'pincode' => '422009',
            ],
            [
                'business_name' => 'Cafe Aroma Nashik',
                'owner_name' => 'Rohan Pawar',
                'email' => 'rohan@cafearoma.test',
                'mobile' => '9876567890',
                'alternate_mobile' => '9823467890',
                'legal_name' => 'Cafe Aroma Nashik',
                'business_type' => 'partnership',
                'gst_number' => '27CAFE1234A1Z3',
                'verification_status' => 'approved',
                'status' => 'active',
                'has_shop_license' => true,
                'has_fssai' => true,
                'admin_note' => 'Cafe demo merchant for food and quick-service workflows.',
                'address_line_1' => 'Shop 2, Gangapur Road',
                'address_line_2' => 'Near Jehan Circle',
                'landmark' => 'Coffee Lane',
                'pincode' => '422013',
            ],
            [
                'business_name' => 'HomeCraft Living',
                'owner_name' => 'Meera Kulkarni',
                'email' => 'meera@homecraft.test',
                'mobile' => '9876578901',
                'alternate_mobile' => '9823478901',
                'legal_name' => 'HomeCraft Living LLP',
                'business_type' => 'partnership',
                'gst_number' => '27HOME1234L1Z6',
                'verification_status' => 'approved',
                'status' => 'active',
                'has_shop_license' => true,
                'has_fssai' => null,
                'admin_note' => 'Home and furniture demo merchant.',
                'address_line_1' => 'Unit 8, Home Plaza',
                'address_line_2' => 'Mumbai Naka',
                'landmark' => 'Near Dwarka Circle',
                'pincode' => '422011',
            ],
            [
                'business_name' => 'FitZone Sports',
                'owner_name' => 'Nikhil More',
                'email' => 'nikhil@fitzone.test',
                'mobile' => '9876589012',
                'alternate_mobile' => '9823489012',
                'legal_name' => 'FitZone Sports',
                'business_type' => 'proprietorship',
                'gst_number' => '27SPRT1234F1Z8',
                'verification_status' => 'approved',
                'status' => 'active',
                'has_shop_license' => true,
                'has_fssai' => null,
                'admin_note' => 'Sports and fitness demo merchant.',
                'address_line_1' => 'Shop 11, Sports Complex Road',
                'address_line_2' => 'College Road',
                'landmark' => 'Near Stadium',
                'pincode' => '422005',
            ],
            [
                'business_name' => 'PageTurner Books',
                'owner_name' => 'Sneha Borse',
                'email' => 'sneha@pageturner.test',
                'mobile' => '9876590123',
                'alternate_mobile' => '9823490123',
                'legal_name' => 'PageTurner Books',
                'business_type' => 'proprietorship',
                'gst_number' => '27BOOK1234P1Z2',
                'verification_status' => 'approved',
                'status' => 'active',
                'has_shop_license' => true,
                'has_fssai' => null,
                'admin_note' => 'Books and stationery demo merchant.',
                'address_line_1' => 'Shop 6, College Road',
                'address_line_2' => 'Near Library Chowk',
                'landmark' => 'Book Lane',
                'pincode' => '422005',
            ],
            [
                'business_name' => 'Local Finds General Store',
                'owner_name' => 'Anil Jadhav',
                'email' => 'anil@localfinds.test',
                'mobile' => '9876509876',
                'alternate_mobile' => '9823409876',
                'legal_name' => 'Local Finds General Store',
                'business_type' => 'proprietorship',
                'gst_number' => '27GENL1234S1Z5',
                'verification_status' => 'submitted',
                'status' => 'active',
                'has_shop_license' => true,
                'has_fssai' => null,
                'admin_note' => 'Generic Other shop type demo merchant.',
                'address_line_1' => 'Shop 1, Main Road',
                'address_line_2' => 'Old Nashik',
                'landmark' => 'Near Clock Tower',
                'pincode' => '422001',
            ],
        ];
    }
}
