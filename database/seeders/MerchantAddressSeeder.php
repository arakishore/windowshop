<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class MerchantAddressSeeder extends Seeder
{
    /**
     * Seed one business address per demo merchant.
     */
    public function run(): void
    {
        $now = now();

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
            throw new RuntimeException('India, Maharashtra and Nashik must exist before seeding merchant addresses.');
        }

        foreach ($this->addresses() as $address) {
            $merchantId = DB::table('merchant_profiles')
                ->join('users', 'users.id', '=', 'merchant_profiles.user_id')
                ->where('users.email', $address['email'])
                ->value('merchant_profiles.id');

            if ($merchantId === null) {
                throw new RuntimeException("Demo merchant profile not found for {$address['email']}.");
            }

            DB::table('merchant_addresses')->updateOrInsert(
                [
                    'merchant_id' => $merchantId,
                    'address_type' => 'business',
                ],
                fn (bool $exists) => [
                    'address_line_1' => $address['address_line_1'],
                    'address_line_2' => $address['address_line_2'],
                    'landmark' => $address['landmark'],
                    'country_id' => $countryId,
                    'state_id' => $stateId,
                    'city_id' => $cityId,
                    'pincode' => $address['pincode'],
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
    }

    /**
     * @return array<int, array<string, string|null>>
     */
    private function addresses(): array
    {
        return [
            [
                'email' => 'priya@vanawomen.test',
                'address_line_1' => 'Shop 12, College Road',
                'address_line_2' => 'Near Big Bazaar',
                'landmark' => 'Canada Corner',
                'pincode' => '422005',
            ],
            [
                'email' => 'neha@gracebloom.test',
                'address_line_1' => 'Unit 4, Fashion Street',
                'address_line_2' => 'MG Road',
                'landmark' => 'Near CBS Signal',
                'pincode' => '422001',
            ],
            [
                'email' => 'pooja@rangoliethnic.test',
                'address_line_1' => 'Plot 18, Gangapur Road',
                'address_line_2' => null,
                'landmark' => 'Near Jehan Circle',
                'pincode' => '422013',
            ],
            [
                'email' => 'rahul@urbanman.test',
                'address_line_1' => 'Shop 7, City Centre Mall',
                'address_line_2' => 'Untwadi Road',
                'landmark' => 'Lavate Nagar',
                'pincode' => '422009',
            ],
            [
                'email' => 'amit@classicmenswear.test',
                'address_line_1' => 'Ground Floor, Main Road',
                'address_line_2' => 'Shalimar',
                'landmark' => 'Near Old CBS',
                'pincode' => '422001',
            ],
        ];
    }
}
