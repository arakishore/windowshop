<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Query-builder shop creation bypasses model events; include inactive shops.
        DB::table('shops')->select('id')->orderBy('id')->chunkById(200, function ($shops): void {
            $now = now();
            $pages = [];

            foreach ($shops as $shop) {
                foreach (['about' => 'About Us', 'privacy' => 'Privacy Policy', 'terms' => 'Terms & Conditions', 'policies' => 'Shop Policies'] as $key => $title) {
                    $pages[] = [
                        'shop_id' => $shop->id,
                        'page_type' => 'standard',
                        'page_key' => $key,
                        'title' => $title,
                        'slug' => $key,
                        'body' => null,
                        'status' => 'draft',
                        'published_at' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            DB::table('shop_pages')->insertOrIgnore($pages);
        });
    }

    public function down(): void
    {
        // Existing or edited pages must survive a backfill migration rollback.
    }
};
