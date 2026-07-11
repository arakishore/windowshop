<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class MerchantDemoSeeder extends Seeder
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
            }
        });
    }

    /**
     * @return array<int, array<string, string>>
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
            ],
        ];
    }
}
