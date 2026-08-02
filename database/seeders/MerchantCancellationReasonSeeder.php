<?php

namespace Database\Seeders;

use App\Models\MerchantCancellationReason;
use App\Models\MerchantProfile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MerchantCancellationReasonSeeder extends Seeder
{
    /**
     * @var array<int, string>
     */
    private const PERMISSIONS = [
        'merchant.cancellation-reasons.view',
        'merchant.cancellation-reasons.create',
        'merchant.cancellation-reasons.update',
        'merchant.cancellation-reasons.delete',
        'merchant.cancellation-reasons.restore',
    ];

    public function run(): void
    {
        $this->seedPermissions();

        MerchantProfile::query()
            ->select('id')
            ->eachById(fn (MerchantProfile $merchant): mixed => $this->seedDefaultsForMerchant($merchant));
    }

    public function seedDefaultsForMerchant(MerchantProfile $merchant): void
    {
        foreach (MerchantCancellationReason::defaults() as $code => $default) {
            MerchantCancellationReason::query()->updateOrCreate(
                [
                    'merchant_id' => $merchant->getKey(),
                    'code' => $code,
                ],
                [
                    'uuid' => MerchantCancellationReason::query()
                        ->where('merchant_id', $merchant->getKey())
                        ->where('code', $code)
                        ->value('uuid') ?: (string) Str::uuid(),
                    ...$default,
                ],
            );
        }
    }

    private function seedPermissions(): void
    {
        $now = now();
        $permissionIds = [];

        foreach (self::PERMISSIONS as $slug) {
            DB::table('auth_permissions')->updateOrInsert(
                ['slug' => $slug],
                [
                    'uuid' => DB::table('auth_permissions')->where('slug', $slug)->value('uuid') ?: (string) Str::uuid(),
                    'name' => Str::headline(Str::afterLast($slug, '.')),
                    'module' => 'merchant_cancellation_reasons',
                    'description' => 'Allows merchant users to '.str_replace('-', ' ', Str::afterLast($slug, '.')).' cancellation reasons.',
                    'status' => 'active',
                    'updated_at' => $now,
                    'created_at' => DB::table('auth_permissions')->where('slug', $slug)->value('created_at') ?: $now,
                ],
            );

            $permissionIds[] = (int) DB::table('auth_permissions')->where('slug', $slug)->value('id');
        }

        $merchantRoleId = DB::table('auth_roles')->where('slug', 'merchant')->value('id');

        if (! $merchantRoleId) {
            return;
        }

        foreach ($permissionIds as $permissionId) {
            DB::table('auth_role_permissions')->updateOrInsert(
                [
                    'role_id' => $merchantRoleId,
                    'permission_id' => $permissionId,
                ],
                [
                    'updated_at' => $now,
                    'created_at' => DB::table('auth_role_permissions')
                        ->where('role_id', $merchantRoleId)
                        ->where('permission_id', $permissionId)
                        ->value('created_at') ?: $now,
                ],
            );
        }
    }
}
