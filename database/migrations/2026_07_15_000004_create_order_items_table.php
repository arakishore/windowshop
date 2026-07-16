<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->string('product_name');
            $table->string('product_image', 500)->nullable();
            $table->string('variant_name')->nullable();
            $table->string('sku')->nullable();
            $table->string('barcode', 100)->nullable();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_mrp', 14, 2)->default(0);
            $table->decimal('unit_price', 14, 2)->default(0);
            $table->decimal('unit_discount', 14, 2)->default(0);
            $table->decimal('line_subtotal', 14, 2)->default(0);
            $table->decimal('line_discount', 14, 2)->default(0);
            $table->decimal('line_tax', 14, 2)->default(0);
            $table->decimal('line_total', 14, 2)->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('order_id', 'order_items_order_idx');
            $table->index('product_id', 'order_items_product_idx');
            $table->index('product_variant_id', 'order_items_variant_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
