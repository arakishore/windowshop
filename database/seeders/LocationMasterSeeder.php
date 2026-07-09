<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LocationMasterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = now();

        DB::table('loc_regions')->updateOrInsert(
            ['name' => 'Asia'],
            fn (bool $exists) => [
                'flag' => true,
                'wiki_data_id' => 'Q48',
                'updated_at' => $now,
                ...($exists ? [] : [
                    'uuid' => (string) Str::uuid(),
                    'created_at' => $now,
                ]),
            ],
        );

        $regionId = DB::table('loc_regions')
            ->where('name', 'Asia')
            ->value('id');

        DB::table('loc_subregions')->updateOrInsert(
            ['region_id' => $regionId, 'name' => 'Southern Asia'],
            fn (bool $exists) => [
                'flag' => true,
                'wiki_data_id' => 'Q771405',
                'deleted_at' => null,
                'updated_at' => $now,
                ...($exists ? [] : [
                    'uuid' => (string) Str::uuid(),
                    'created_at' => $now,
                ]),
            ],
        );

        $subregionId = DB::table('loc_subregions')
            ->where('region_id', $regionId)
            ->where('name', 'Southern Asia')
            ->value('id');

        DB::table('loc_countries')->updateOrInsert(
            ['iso2' => 'IN'],
            fn (bool $exists) => [
                'name' => 'India',
                'iso3' => 'IND',
                'numeric_code' => '356',
                'phonecode' => '91',
                'capital' => 'New Delhi',
                'currency' => 'INR',
                'currency_name' => 'Indian Rupee',
                'currency_symbol' => '₹',
                'region_id' => $regionId,
                'subregion_id' => $subregionId,
                'nationality' => 'Indian',
                'status' => true,
                'deleted_at' => null,
                'updated_at' => $now,
                ...($exists ? [] : [
                    'uuid' => (string) Str::uuid(),
                    'created_at' => $now,
                ]),
            ],
        );

        $countryId = DB::table('loc_countries')
            ->where('iso2', 'IN')
            ->value('id');

        DB::table('loc_states')->updateOrInsert(
            ['country_id' => $countryId, 'iso2' => 'MH'],
            fn (bool $exists) => [
                'name' => 'Maharashtra',
                'country_code' => 'IN',
                'iso3166_2' => 'IN-MH',
                'status' => true,
                'deleted_at' => null,
                'updated_at' => $now,
                ...($exists ? [] : [
                    'uuid' => (string) Str::uuid(),
                    'created_at' => $now,
                ]),
            ],
        );

        $stateId = DB::table('loc_states')
            ->where('country_id', $countryId)
            ->where('iso2', 'MH')
            ->value('id');

        DB::table('loc_cities')->updateOrInsert(
            [
                'country_id' => $countryId,
                'state_id' => $stateId,
                'name' => 'Nashik',
            ],
            fn (bool $exists) => [
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
