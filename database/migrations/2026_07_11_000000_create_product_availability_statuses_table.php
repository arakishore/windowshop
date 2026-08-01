<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_availability_statuses', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('merchant_id')
                ->constrained('merchant_profiles')
                ->cascadeOnDelete();
            $table->string('code', 80);
            $table->string('name', 120);
            $table->text('customer_description')->nullable();
            $table->boolean('purchase_allowed')->default(false);
            $table->string('badge_type', 30)->default('secondary')->comment('success,danger,warning,secondary');
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->string('status', 30)->default('active')->comment('active,inactive')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['merchant_id', 'code'], 'availability_statuses_merchant_code_unique');
            $table->index(['merchant_id', 'status', 'sort_order'], 'availability_statuses_merchant_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_availability_statuses');
    }
};
