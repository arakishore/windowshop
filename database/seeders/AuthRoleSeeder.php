<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AuthRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = now();

        DB::table('auth_roles')->upsert([
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Super Admin',
                'slug' => 'super_admin',
                'description' => 'Full platform administration access.',
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Admin',
                'slug' => 'admin',
                'description' => 'Platform administration access based on assigned permissions.',
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Merchant',
                'slug' => 'merchant',
                'description' => 'Merchant account access.',
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Customer',
                'slug' => 'customer',
                'description' => 'Customer account access.',
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ], ['slug'], ['name', 'description', 'status', 'updated_at']);
    }
}
