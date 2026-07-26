<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_refunds', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('refund_number')->unique();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('merchant_id')->constrained('merchant_profiles')->restrictOnDelete();
            $table->foreignId('shop_id')->constrained('shops')->restrictOnDelete();
            $table->foreignId('return_reason_id')->nullable()->constrained('merchant_return_reasons')->nullOnDelete();
            $table->string('reason_name', 120);
            $table->string('refund_method', 30)->default('original');
            $table->decimal('refund_subtotal', 14, 2)->default(0);
            $table->decimal('refund_tax', 14, 2)->default(0);
            $table->decimal('refund_total', 14, 2)->default(0);
            $table->string('status', 30)->default('completed')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'created_at'], 'order_refunds_order_created_idx');
            $table->index(['shop_id', 'created_at'], 'order_refunds_shop_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_refunds');
    }
};
