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
        Schema::create('products', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('merchant_id')
                ->constrained('merchant_profiles')
                ->restrictOnDelete();
            $table->foreignId('shop_id')
                ->constrained('shops')
                ->restrictOnDelete();
            $table->foreignId('root_product_category_id')
                ->constrained('product_categories')
                ->restrictOnDelete();
            $table->foreignId('product_category_id')
                ->constrained('product_categories')
                ->restrictOnDelete();
            $table->foreignId('brand_id')
                ->nullable()
                ->constrained('brands')
                ->nullOnDelete();
            $table->foreignId('primary_image_id')
                ->nullable();
            $table->string('product_name');
            $table->string('slug')->nullable()->unique();
            $table->text('short_description')->nullable();
            $table->longText('description')->nullable();
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->string('status', 30)
                ->default('draft')
                ->comment('draft,active,inactive,archived')
                ->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['merchant_id', 'shop_id', 'status'], 'products_merchant_shop_status_idx');
            $table->index(['shop_id', 'root_product_category_id'], 'products_shop_root_category_idx');
            $table->index(['shop_id', 'product_category_id'], 'products_shop_product_category_idx');
            $table->index(['shop_id', 'brand_id'], 'products_shop_brand_idx');
            $table->index(['shop_id', 'slug'], 'products_shop_slug_idx');
            $table->index(['status', 'published_at'], 'products_status_publish_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
