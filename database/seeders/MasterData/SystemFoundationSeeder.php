<?php

namespace Database\Seeders\MasterData;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SystemFoundationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call(AuthRoleSeeder::class);
        $this->call(LocationSeeder::class);
        $this->call(ShopCategorySeeder::class);
        $this->call(ShopAudienceSeeder::class);

        $now = now();

        $groups = [
            ['name' => 'General', 'slug' => 'general', 'sort_order' => 10],
            ['name' => 'Localization', 'slug' => 'localization', 'sort_order' => 20],
            ['name' => 'Email', 'slug' => 'email', 'sort_order' => 30],
            ['name' => 'SMS', 'slug' => 'sms', 'sort_order' => 40],
            ['name' => 'WhatsApp', 'slug' => 'whatsapp', 'sort_order' => 50],
            ['name' => 'Payment', 'slug' => 'payment', 'sort_order' => 60],
            ['name' => 'Shipping', 'slug' => 'shipping', 'sort_order' => 70],
            ['name' => 'AI', 'slug' => 'ai', 'sort_order' => 80],
            ['name' => 'Security', 'slug' => 'security', 'sort_order' => 90],
        ];

        foreach ($groups as $group) {
            DB::table('system_setting_groups')->updateOrInsert(
                ['slug' => $group['slug']],
                fn (bool $exists) => [
                    'name' => $group['name'],
                    'sort_order' => $group['sort_order'],
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

        $groupIds = DB::table('system_setting_groups')
            ->whereIn('slug', ['general', 'localization'])
            ->pluck('id', 'slug');

        $settings = [
            [
                'group_id' => $groupIds['general'],
                'key' => 'marketplace_name',
                'label' => 'Marketplace Name',
                'value' => 'WindowShop',
                'sort_order' => 10,
            ],
            [
                'group_id' => $groupIds['localization'],
                'key' => 'default_country_code',
                'label' => 'Default Country Code',
                'value' => 'IN',
                'sort_order' => 20,
            ],
            [
                'group_id' => $groupIds['localization'],
                'key' => 'default_state_code',
                'label' => 'Default State Code',
                'value' => 'MH',
                'sort_order' => 30,
            ],
            [
                'group_id' => $groupIds['localization'],
                'key' => 'default_city_name',
                'label' => 'Default City Name',
                'value' => 'Nashik',
                'sort_order' => 40,
            ],
            [
                'group_id' => $groupIds['localization'],
                'key' => 'default_language',
                'label' => 'Default Language',
                'value' => 'en',
                'sort_order' => 50,
            ],
            [
                'group_id' => $groupIds['localization'],
                'key' => 'default_currency',
                'label' => 'Default Currency',
                'value' => 'INR',
                'sort_order' => 60,
            ],
            [
                'group_id' => $groupIds['localization'],
                'key' => 'default_timezone',
                'label' => 'Default Timezone',
                'value' => 'Asia/Kolkata',
                'sort_order' => 70,
            ],
        ];

        foreach ($settings as $setting) {
            DB::table('system_settings')->updateOrInsert(
                ['key' => $setting['key']],
                fn (bool $exists) => [
                    'group_id' => $setting['group_id'],
                    'label' => $setting['label'],
                    'value' => $setting['value'],
                    'value_type' => 'string',
                    'is_public' => false,
                    'is_encrypted' => false,
                    'sort_order' => $setting['sort_order'],
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
}
