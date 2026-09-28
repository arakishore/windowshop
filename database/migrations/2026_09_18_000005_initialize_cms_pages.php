<?php

use App\Services\Cms\CmsPageInitializer;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(CmsPageInitializer::class)->initialize();
    }

    public function down(): void
    {
        // Admin-edited content must survive a backfill rollback.
    }
};
