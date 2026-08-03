<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_exchange_return_items', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->foreignId('order_exchange_id')->constrained('order_exchanges')->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained('order_items')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_return_value', 14, 2)->default(0);
            $table->decimal('line_tax', 14, 2)->default(0);
            $table->decimal('line_total', 14, 2)->default(0);
            $table->boolean('restocked')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('order_exchange_id', 'order_exchange_items_exchange_idx');
            $table->index('order_item_id', 'order_exchange_items_order_item_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_exchange_return_items');
    }
};
