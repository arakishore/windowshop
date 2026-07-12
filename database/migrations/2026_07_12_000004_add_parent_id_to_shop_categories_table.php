<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasColumn('shop_categories', 'parent_id')) {
            return;
        }

        try {
            Schema::table('shop_categories', function (Blueprint $table): void {
                $table->dropUnique(['name']);
            });
        } catch (\Throwable) {
        }

        Schema::table('shop_categories', function (Blueprint $table): void {
            $table->foreignId('parent_id')
                ->nullable()
                ->after('uuid')
                ->constrained('shop_categories')
                ->nullOnDelete();

            $table->unique(
                ['parent_id', 'name'],
                'shop_categories_parent_name_unique',
            );

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('shop_categories', 'parent_id')) {
            return;
        }

        Schema::table('shop_categories', function (Blueprint $table): void {
            $table->dropUnique('shop_categories_parent_name_unique');
            $table->dropConstrainedForeignId('parent_id');
        });
    }
};
