<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = now();
        $email = 'admin@windowshop.test';

        $exists = DB::table('users')->where('email', $email)->exists();

        DB::table('users')->updateOrInsert(
            ['email' => $email],
            array_merge([
                'name' => 'Super Administrator',
                'password' => Hash::make('password'),
                'status' => 'active',
                'deleted_at' => null,
                'updated_at' => $now,
            ], $exists ? [] : [
                'uuid' => (string) Str::uuid(),
                'created_at' => $now,
            ])
        );

        $userId = DB::table('users')
            ->where('email', $email)
            ->value('id');

        $roleId = DB::table('auth_roles')
            ->where('slug', 'super_admin')
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->value('id');

        if ($roleId === null) {
            throw new RuntimeException('The active super_admin role must be seeded first.');
        }

        DB::table('auth_user_roles')->updateOrInsert(
            [
                'user_id' => $userId,
                'role_id' => $roleId,
            ],
            fn(bool $exists) => [
                'updated_at' => $now,
                ...($exists ? [] : ['created_at' => $now]),
            ],
        );
    }
}
