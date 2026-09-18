<?php

use App\Services\Merchant\ShopPageInitializer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $initializer = app(ShopPageInitializer::class);

        DB::table('shops')->select('id')->orderBy('id')->chunkById(200, function ($shops) use ($initializer): void {
            $initializer->initializeMany($shops->pluck('id'));
        });
    }

    public function down(): void
    {
        // Generated text may have been edited; rollback must not remove it.
    }
};
