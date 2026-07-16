<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('order_number')->unique();
            $table->foreignId('merchant_id')->constrained('merchant_profiles')->restrictOnDelete();
            $table->foreignId('shop_id')->constrained('shops')->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('created_source', 30)->default('pos')->index();
            $table->string('fulfilment_type', 30)->default('counter');
            $table->string('order_status', 30)->default('pending')->index();
            $table->string('payment_method', 30)->default('cash');
            $table->string('payment_status', 30)->default('unpaid')->index();
            $table->string('currency_code', 3)->default('INR');
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('discount_total', 14, 2)->default(0);
            $table->decimal('shipping_total', 14, 2)->default(0);
            $table->decimal('tax_total', 14, 2)->default(0);
            $table->decimal('rounding_adjustment', 14, 2)->default(0);
            $table->decimal('grand_total', 14, 2)->default(0);
            $table->decimal('amount_paid', 14, 2)->default(0);
            $table->decimal('change_amount', 14, 2)->default(0);
            $table->string('customer_name')->nullable();
            $table->string('customer_mobile', 30)->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['merchant_id', 'created_at'], 'orders_merchant_created_idx');
            $table->index(['shop_id', 'created_at'], 'orders_shop_created_idx');
            $table->index(['shop_id', 'order_status'], 'orders_shop_status_idx');
            $table->index(['shop_id', 'payment_status'], 'orders_shop_payment_status_idx');
            $table->index('customer_id', 'orders_customer_idx');
            $table->index('deleted_at', 'orders_deleted_at_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
