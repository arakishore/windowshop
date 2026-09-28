<?php

namespace Database\Seeders\MasterData;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PublicContactSettingSeeder extends Seeder
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

        $settings = [
            ['contact.support_email', 'Support Email', 'Public customer-support email address.', 'string', 30],
            ['contact.phone', 'Support Phone', 'Public customer-support telephone number.', 'string', 40],
            ['contact.whatsapp', 'Support WhatsApp', 'Public WhatsApp support number, including country code.', 'string', 50],
            ['contact.office_address', 'Office Address', 'Public WindowShop office or registered address.', 'text', 60],
            ['contact.support_hours', 'Support Hours', 'Public telephone and general support hours.', 'text', 70],
            ['contact.whatsapp_hours', 'WhatsApp Hours', 'Public WhatsApp availability hours; blank uses general support hours.', 'text', 80],
            ['social.facebook', 'Facebook URL', 'Canonical public Facebook profile URL.', 'string', 90],
            ['social.instagram', 'Instagram URL', 'Canonical public Instagram profile URL.', 'string', 100],
            ['social.twitter', 'X / Twitter URL', 'Canonical public X or Twitter profile URL.', 'string', 110],
            ['social.youtube', 'YouTube URL', 'Canonical public YouTube profile URL.', 'string', 120],
            ['social.linkedin', 'LinkedIn URL', 'Canonical public LinkedIn profile URL.', 'string', 130],
        ];

        foreach ($settings as [$key, $label, $description, $type, $sortOrder]) {
            $existing = DB::table('system_settings')->where('key', $key)->first();
            $attributes = [
                'group_id' => $groupId,
                'label' => $label,
                'value_type' => $type,
                'is_public' => true,
                'is_encrypted' => false,
                'description' => $description,
                'sort_order' => $sortOrder,
                'status' => 'active',
                'deleted_at' => null,
                'updated_at' => $now,
            ];

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
}
