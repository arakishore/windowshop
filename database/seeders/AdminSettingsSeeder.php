<?php

namespace Database\Seeders;

use App\Services\Admin\AdminSettingsInitializer;
use Illuminate\Database\Seeder;

class AdminSettingsSeeder extends Seeder
{
    public function run(): void
    {
        app(AdminSettingsInitializer::class)->initialize();
    }
}
