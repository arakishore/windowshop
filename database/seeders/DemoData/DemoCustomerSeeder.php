<?php

namespace Database\Seeders\DemoData;

use App\Enums\UserRegistrationSource;
use App\Services\Shared\MobileNumberNormalizer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class DemoCustomerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = now();
        $normalizer = app(MobileNumberNormalizer::class);

        DB::transaction(function () use ($now, $normalizer): void {
            $roleId = DB::table('auth_roles')
                ->where('slug', 'customer')
                ->where('status', 'active')
                ->whereNull('deleted_at')
                ->value('id');

            if ($roleId === null) {
                throw new RuntimeException('The active customer role must exist before seeding demo customers.');
            }

            $location = $this->defaultLocation();

            foreach ($this->customers() as $customer) {
                $mobile = $normalizer->normalize($customer['mobile'], '+91');
                $userId = null;

                if ($customer['email'] !== null) {
                    $userExists = DB::table('users')
                        ->where('email', $customer['email'])
                        ->exists();

                    DB::table('users')->updateOrInsert(
                        ['email' => $customer['email']],
                        array_merge([
                            'name' => $customer['name'],
                            'mobile' => $mobile['mobile'],
                            'password' => Hash::make('password'),
                            'status' => 'active',
                            'deleted_at' => null,
                            'updated_at' => $now,
                        ], $userExists ? [] : [
                            'uuid' => (string) Str::uuid(),
                            'registration_source' => UserRegistrationSource::WEB->value,
                            'created_at' => $now,
                        ]),
                    );

                    $userId = DB::table('users')
                        ->where('email', $customer['email'])
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
                }

                $customerExists = DB::table('customers')
                    ->where('mobile_normalized', $mobile['mobile_normalized'])
                    ->exists();

                DB::table('customers')->updateOrInsert(
                    ['mobile_normalized' => $mobile['mobile_normalized']],
                    array_merge([
                        'user_id' => $userId,
                        'name' => $customer['name'],
                        'mobile_country_code' => $mobile['country_code'],
                        'mobile' => $mobile['mobile'],
                        'email' => $customer['email'],
                        'date_of_birth' => $customer['date_of_birth'],
                        'gender' => $customer['gender'],
                        'is_business_customer' => $customer['is_business_customer'],
                        'company_name' => $customer['company_name'],
                        'gst_number' => $customer['gst_number'],
                        'status' => 'active',
                        'deleted_at' => null,
                        'updated_at' => $now,
                    ], $customerExists ? [] : [
                        'uuid' => (string) Str::uuid(),
                        'created_at' => $now,
                    ]),
                );

                $customerId = DB::table('customers')
                    ->where('mobile_normalized', $mobile['mobile_normalized'])
                    ->value('id');

                DB::table('customer_addresses')->updateOrInsert(
                    [
                        'customer_id' => $customerId,
                        'label' => $customer['address']['label'],
                    ],
                    fn (bool $exists) => [
                        'recipient_name' => $customer['name'],
                        'recipient_mobile_country_code' => $mobile['country_code'],
                        'recipient_mobile' => $mobile['mobile'],
                        'recipient_mobile_normalized' => $mobile['mobile_normalized'],
                        'address_line_1' => $customer['address']['address_line_1'],
                        'address_line_2' => $customer['address']['address_line_2'],
                        'landmark' => $customer['address']['landmark'],
                        'country_id' => $location['country_id'],
                        'state_id' => $location['state_id'],
                        'city_id' => $location['city_id'],
                        'postal_code' => $customer['address']['postal_code'],
                        'is_default_shipping' => true,
                        'is_default_billing' => true,
                        'status' => 'active',
                        'deleted_at' => null,
                        'updated_at' => $now,
                        ...($exists ? [] : [
                            'uuid' => (string) Str::uuid(),
                            'created_at' => $now,
                        ]),
                    ],
                );

                foreach ($customer['merchant_emails'] as $merchantEmail) {
                    $merchantId = DB::table('merchant_profiles')
                        ->join('users', 'users.id', '=', 'merchant_profiles.user_id')
                        ->where('users.email', $merchantEmail)
                        ->value('merchant_profiles.id');

                    if ($merchantId === null) {
                        throw new RuntimeException("Demo merchant profile not found for {$merchantEmail}.");
                    }

                    DB::table('merchant_customers')->updateOrInsert(
                        [
                            'merchant_id' => $merchantId,
                            'customer_id' => $customerId,
                        ],
                        fn (bool $exists) => [
                            'customer_code' => $customer['customer_code'],
                            'notes' => $customer['notes'],
                            'trust_status' => $customer['trust_status'],
                            'status' => 'active',
                            'linked_at' => $now,
                            'deleted_at' => null,
                            'updated_at' => $now,
                            ...($exists ? [] : [
                                'uuid' => (string) Str::uuid(),
                                'created_at' => $now,
                            ]),
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
            throw new RuntimeException('India, Maharashtra and Nashik must exist before seeding demo customers.');
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
    private function customers(): array
    {
        return [
            [
                'name' => 'Kishore Mishra',
                'email' => 'kishore@example.test',
                'mobile' => '9876601001',
                'date_of_birth' => '1991-05-14',
                'gender' => 'male',
                'is_business_customer' => false,
                'company_name' => null,
                'gst_number' => null,
                'customer_code' => 'CUST-1001',
                'trust_status' => 'normal',
                'notes' => 'Regular storefront customer for checkout and account demos.',
                'merchant_emails' => ['nikhil@fitzone.test', 'sneha@pageturner.test'],
                'address' => [
                    'label' => 'Home',
                    'address_line_1' => 'Flat 402, Green Heights',
                    'address_line_2' => 'College Road',
                    'landmark' => 'Near BYK College',
                    'postal_code' => '422005',
                ],
            ],
            [
                'name' => 'Aarohi Deshpande',
                'email' => 'aarohi@example.test',
                'mobile' => '9876601002',
                'date_of_birth' => '1994-09-22',
                'gender' => 'female',
                'is_business_customer' => false,
                'company_name' => null,
                'gst_number' => null,
                'customer_code' => 'CUST-1002',
                'trust_status' => 'trusted',
                'notes' => 'Demo customer linked to apparel workflows.',
                'merchant_emails' => ['priya@vanawomen.test'],
                'address' => [
                    'label' => 'Home',
                    'address_line_1' => 'Row House 18, Blossom Park',
                    'address_line_2' => 'Gangapur Road',
                    'landmark' => 'Near Jehan Circle',
                    'postal_code' => '422013',
                ],
            ],
            [
                'name' => 'Ravi Stationers',
                'email' => null,
                'mobile' => '9876601003',
                'date_of_birth' => null,
                'gender' => null,
                'is_business_customer' => true,
                'company_name' => 'Ravi Stationers',
                'gst_number' => '27RVST1234A1Z5',
                'customer_code' => 'CUST-1003',
                'trust_status' => 'normal',
                'notes' => 'POS-style customer without a login account.',
                'merchant_emails' => ['sneha@pageturner.test'],
                'address' => [
                    'label' => 'Shop',
                    'address_line_1' => 'Shop 4, Stationery Lane',
                    'address_line_2' => 'Main Road',
                    'landmark' => 'Near Library Chowk',
                    'postal_code' => '422001',
                ],
            ],
        ];
    }
}
