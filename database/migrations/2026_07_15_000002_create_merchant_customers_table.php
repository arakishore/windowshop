<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchant_customers', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('merchant_id')
                ->constrained('merchant_profiles')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('customer_code', 30);
            $table->string('name', 150);
            $table->string('mobile_country_code', 10)->nullable();
            $table->string('mobile', 30);
            $table->string('mobile_normalized', 30);
            $table->string('email', 190)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('gender', 20)->nullable();
            $table->boolean('is_business_customer')->default(false);
            $table->string('company_name', 150)->nullable();
            $table->string('gst_number', 30)->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamp('linked_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['merchant_id', 'customer_code'], 'merchant_customers_merchant_code_unique');
            $table->unique(['merchant_id', 'mobile_normalized'], 'merchant_customers_merchant_mobile_unique');
            $table->index(['mobile_normalized', 'user_id'], 'merchant_customers_mobile_user_idx');
            $table->index(['merchant_id', 'name'], 'merchant_customers_merchant_name_idx');
            $table->index(['merchant_id', 'status'], 'merchant_customers_merchant_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_customers');
    }
};
