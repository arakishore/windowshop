<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalogue_master_requests', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('merchant_id')->constrained('merchant_profiles')->cascadeOnDelete();
            $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->foreignId('root_product_category_id')->constrained('product_categories');
            $table->string('request_type', 20)->comment('category, attribute');
            $table->string('suggested_name');
            $table->foreignId('parent_product_category_id')->nullable()->constrained('product_categories')->nullOnDelete();
            $table->text('description')->nullable();
            $table->string('example_product_name')->nullable();
            $table->string('status', 20)->default('pending')->comment('pending, approved, rejected, needs_info');
            $table->text('admin_note')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['merchant_id', 'shop_id', 'status'], 'catalogue_requests_merchant_shop_status_idx');
            $table->index(['request_type', 'status'], 'catalogue_requests_type_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalogue_master_requests');
    }
};
