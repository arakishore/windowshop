<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_exchanges', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('exchange_number')->unique();
            $table->foreignId('original_order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('replacement_order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('merchant_id')->constrained('merchant_profiles')->restrictOnDelete();
            $table->foreignId('shop_id')->constrained('shops')->restrictOnDelete();
            $table->decimal('returned_total', 14, 2)->default(0);
            $table->decimal('replacement_total', 14, 2)->default(0);
            $table->decimal('difference_amount', 14, 2)->default(0);
            $table->decimal('amount_collected', 14, 2)->default(0);
            $table->decimal('amount_refunded', 14, 2)->default(0);
            $table->decimal('credit_adjustment_amount', 14, 2)->default(0);
            $table->string('settlement_type', 30)->default('even')->index();
            $table->string('settlement_method', 30)->nullable();
            $table->string('status', 30)->default('completed')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['original_order_id', 'created_at'], 'order_exchanges_original_created_idx');
            $table->index(['replacement_order_id'], 'order_exchanges_replacement_idx');
            $table->index(['shop_id', 'created_at'], 'order_exchanges_shop_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_exchanges');
    }
};
