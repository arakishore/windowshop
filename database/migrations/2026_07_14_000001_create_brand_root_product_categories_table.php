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
        Schema::create('brand_root_product_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('brand_id')
                ->constrained('brands')
                ->cascadeOnDelete();
            $table->foreignId('root_product_category_id')
                ->constrained('product_categories')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['brand_id', 'root_product_category_id'], 'brand_root_category_unique');
            $table->index('root_product_category_id', 'brand_root_category_root_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('brand_root_product_categories');
    }
};
