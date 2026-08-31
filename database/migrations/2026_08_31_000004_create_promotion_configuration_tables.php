<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotion_conditions', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->foreignId('promotion_id')->constrained('promotions')->cascadeOnDelete();
            $table->string('condition_type', 80);
            $table->string('operator', 30)->nullable();
            $table->decimal('value_numeric', 12, 2)->nullable();
            $table->string('value_text')->nullable();
            $table->json('value_json')->nullable();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->timestamps();

            $table->index(['promotion_id', 'condition_type'], 'promotion_conditions_type_idx');
        });

        Schema::create('promotion_rewards', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->foreignId('promotion_id')->constrained('promotions')->cascadeOnDelete();
            $table->string('reward_type', 80)->index();
            $table->string('value_type', 30)->nullable();
            $table->decimal('value_amount', 12, 2)->nullable();
            $table->decimal('value_percent', 7, 2)->nullable();
            $table->decimal('max_discount_amount', 12, 2)->nullable();
            $table->unsignedInteger('buy_quantity')->nullable();
            $table->unsignedInteger('get_quantity')->nullable();
            $table->unsignedInteger('bundle_quantity')->nullable();
            $table->decimal('bundle_price', 12, 2)->nullable();
            $table->json('tier_config')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('promotion_targets', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->foreignId('promotion_id')->constrained('promotions')->cascadeOnDelete();
            $table->string('target_role', 30);
            $table->string('target_type', 30);
            $table->unsignedBigInteger('target_id')->nullable();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->timestamps();

            $table->index(['promotion_id', 'target_role', 'target_type'], 'promotion_targets_role_type_idx');
            $table->index(['target_type', 'target_id'], 'promotion_targets_lookup_idx');
        });

        Schema::create('promotion_coupons', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->foreignId('promotion_id')->constrained('promotions')->cascadeOnDelete();
            $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->string('code', 80);
            $table->string('status', 30)->default('active')->index();
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('per_customer_usage_limit')->nullable();
            $table->timestamps();

            $table->unique(['shop_id', 'code'], 'promotion_coupons_shop_code_unique');
            $table->index(['promotion_id', 'status'], 'promotion_coupons_promotion_status_idx');
            $table->index(['shop_id', 'status', 'starts_at', 'ends_at'], 'promotion_coupons_shop_status_dates_idx');
        });

        Schema::create('promotion_redemptions', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->foreignId('promotion_id')->constrained('promotions')->cascadeOnDelete();
            $table->foreignId('promotion_coupon_id')->nullable()->constrained('promotion_coupons')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('shop_id')->constrained('shops')->restrictOnDelete();
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->string('status', 30)->default('redeemed')->index();
            $table->dateTime('redeemed_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['promotion_id', 'status'], 'promotion_redemptions_promotion_status_idx');
            $table->index(['shop_id', 'customer_id', 'status'], 'promotion_redemptions_customer_status_idx');
            $table->index(['promotion_coupon_id', 'status'], 'promotion_redemptions_coupon_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_redemptions');
        Schema::dropIfExists('promotion_coupons');
        Schema::dropIfExists('promotion_targets');
        Schema::dropIfExists('promotion_rewards');
        Schema::dropIfExists('promotion_conditions');
    }
};
