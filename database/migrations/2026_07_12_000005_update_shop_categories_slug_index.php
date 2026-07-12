<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        try {
            Schema::table('shop_categories', function (Blueprint $table): void {
                $table->dropUnique('shop_categories_parent_slug_unique');
            });
        } catch (\Throwable) {
        }

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE shop_categories MODIFY slug VARCHAR(255) NULL');
        }

        try {
            Schema::table('shop_categories', function (Blueprint $table): void {
                $table->unique('slug');
            });
        } catch (\Throwable) {
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            Schema::table('shop_categories', function (Blueprint $table): void {
                $table->dropUnique(['slug']);
            });
        } catch (\Throwable) {
        }

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE shop_categories MODIFY slug VARCHAR(255) NOT NULL');
        }

        try {
            Schema::table('shop_categories', function (Blueprint $table): void {
                $table->unique(['parent_id', 'slug'], 'shop_categories_parent_slug_unique');
            });
        } catch (\Throwable) {
        }
    }
};
