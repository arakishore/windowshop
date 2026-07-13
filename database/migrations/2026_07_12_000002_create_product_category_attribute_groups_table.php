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
        Schema::create('product_category_attribute_groups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_category_id');
            $table->foreignId('product_attribute_group_id');
            $table->boolean('is_required')
                ->default(false)
                ->comment('Whether this attribute is required for products in this category');
            $table->boolean('is_variant')
                ->default(false)
                ->comment('Whether this attribute generates product variants');
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->timestamps();

            $table->unique(
                ['product_category_id', 'product_attribute_group_id'],
                'product_category_attribute_group_unique',
            );
            $table->index(
                ['product_category_id', 'is_variant', 'sort_order'],
                'product_category_attribute_variant_idx',
            );

            $table->foreign('product_category_id', 'pcag_category_fk')
                ->references('id')
                ->on('product_categories')
                ->cascadeOnDelete();
            $table->foreign('product_attribute_group_id', 'pcag_group_fk')
                ->references('id')
                ->on('product_attribute_groups')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_category_attribute_groups');
    }
};
