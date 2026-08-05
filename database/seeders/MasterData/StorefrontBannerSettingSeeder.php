<?php

namespace Database\Seeders\MasterData;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StorefrontBannerSettingSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        DB::table('system_setting_groups')->updateOrInsert(
            ['slug' => 'storefront-banner'],
            fn (bool $exists) => [
                'name' => 'Storefront Banner',
                'description' => 'Global configuration for storefront banner behaviour.',
                'sort_order' => 100,
                'status' => 'active',
                'deleted_at' => null,
                'updated_at' => $now,
                ...($exists ? [] : [
                    'uuid' => (string) Str::uuid(),
                    'created_at' => $now,
                ]),
            ],
        );

        $groupId = DB::table('system_setting_groups')
            ->where('slug', 'storefront-banner')
            ->value('id');

        DB::table('system_settings')->updateOrInsert(
            ['key' => 'storefront_banner.max_per_shop'],
            fn (bool $exists) => [
                'group_id' => $groupId,
                'label' => 'Maximum Banners Per Shop',
                'value' => '3',
                'value_type' => 'integer',
                'is_public' => false,
                'is_encrypted' => false,
                'description' => 'Maximum number of banner slots allowed for each merchant shop.',
                'sort_order' => 10,
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
