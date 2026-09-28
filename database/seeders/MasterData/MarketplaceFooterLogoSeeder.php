<?php

namespace Database\Seeders\MasterData;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MarketplaceFooterLogoSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $group = DB::table('system_setting_groups')->where('slug', 'marketplace')->first();

        if ($group === null) {
            $groupId = DB::table('system_setting_groups')->insertGetId([
                'uuid' => (string) Str::uuid(),
                'name' => 'Marketplace',
                'slug' => 'marketplace',
                'description' => 'Public marketplace identity and contact information.',
                'sort_order' => 15,
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            $groupId = $group->id;
        }

        $key = 'marketplace.footer_logo';
        $attributes = [
            'group_id' => $groupId,
            'label' => 'Footer Logo',
            'value_type' => 'string',
            'is_public' => false,
            'is_encrypted' => false,
            'description' => 'Optional logo used in the storefront footer. A light/white logo is recommended for dark footer backgrounds.',
            'sort_order' => 15,
            'status' => 'active',
            'deleted_at' => null,
            'updated_at' => $now,
        ];

        $existing = DB::table('system_settings')->where('key', $key)->first();

        if ($existing === null) {
            DB::table('system_settings')->insert([
                ...$attributes,
                'uuid' => (string) Str::uuid(),
                'key' => $key,
                'value' => null,
                'created_at' => $now,
            ]);
        } else {
            DB::table('system_settings')->where('id', $existing->id)->update($attributes);
        }
    }
}
