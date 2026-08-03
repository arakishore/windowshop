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
            $table->foreignId('availability_status_id')
                ->nullable()
                ->constrained('product_availability_statuses')
                ->nullOnDelete();
            $table->string('sku')->nullable();
            $table->string('barcode', 100)->nullable()->index();
            $table->string('name')->nullable();
            $table->decimal('mrp', 12, 2);
            $table->decimal('selling_price', 12, 2);
            $table->decimal('cost_price', 12, 2)->nullable();
            $table->decimal('stock_quantity', 12, 3)->default(0);
            $table->decimal('low_stock_threshold', 12, 3)->default(0);
            $table->boolean('allow_decimal_quantity')->default(false);
            $table->decimal('quantity_increment', 12, 3)->default(1);
            $table->decimal('minimum_order_quantity', 12, 3)->default(1);
            $table->decimal('maximum_order_quantity', 12, 3)->nullable();
            $table->decimal('purchase_quantity_multiple', 12, 3)->default(1);
            $table->boolean('allow_backorder')->default(false);
            $table->boolean('is_sellable')->default(true);
            $table->boolean('is_default')
                ->default(false)
                ->comment('Default variant selected when the product page first loads')
                ->index();
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
            $table->index(['product_id', 'availability_status_id'], 'product_variants_availability_status_idx');
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
