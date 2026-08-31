<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('merchant_id')->constrained('merchant_profiles')->restrictOnDelete();
            $table->foreignId('shop_id')->constrained('shops')->restrictOnDelete();
            $table->foreignId('promotion_template_id')->constrained('promotion_templates')->restrictOnDelete();
            $table->string('name', 180);
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('status', 30)->default('draft')->index();
            $table->string('activation_type', 30)->default('automatic')->index();
            $table->string('origin', 30)->default('merchant')->index();
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->boolean('is_combinable')->default(false);
            $table->integer('priority')->default(0)->index();
            $table->unsignedInteger('total_usage_limit')->nullable();
            $table->unsignedInteger('per_customer_usage_limit')->nullable();
            $table->boolean('new_customer_only')->default(false);
            $table->string('refund_policy_mode', 30)->default('inherit');
            $table->unsignedInteger('refund_window_days')->nullable();
            $table->string('exchange_policy_mode', 30)->default('inherit');
            $table->unsignedInteger('exchange_window_days')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['shop_id', 'slug'], 'promotions_shop_slug_unique');
            $table->index(['merchant_id', 'shop_id', 'status'], 'promotions_owner_status_idx');
            $table->index(['shop_id', 'activation_type', 'status'], 'promotions_shop_activation_status_idx');
            $table->index(['shop_id', 'promotion_template_id', 'origin'], 'promotions_shop_template_origin_idx');
            $table->index(['status', 'starts_at', 'ends_at'], 'promotions_lifecycle_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotions');
    }
};
