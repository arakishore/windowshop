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
        Schema::create('product_variants', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();
            $table->foreignId('shop_id')
                ->constrained('shops')
                ->restrictOnDelete();
            $table->string('sku')->nullable();
            $table->string('barcode', 100)->nullable()->index();
            $table->string('name')->nullable();
            $table->decimal('mrp', 12, 2);
            $table->decimal('selling_price', 12, 2);
            $table->decimal('cost_price', 12, 2)->nullable();
            $table->unsignedInteger('stock_quantity')->default(0);
            $table->unsignedInteger('low_stock_threshold')->default(0);
            $table->boolean('is_default')->default(false)->index();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->string('status', 30)
                ->default('active')
                ->comment('active,inactive')
                ->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['product_id', 'status'], 'product_variants_product_status_idx');
            $table->index(['product_id', 'is_default'], 'product_variants_product_default_idx');
            $table->unique(['shop_id', 'sku'], 'product_variants_shop_sku_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
