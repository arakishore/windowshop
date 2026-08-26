<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')
                ->nullable()
                ->unique()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('name', 150);
            $table->string('mobile_country_code', 10)->nullable();
            $table->string('mobile', 30)->nullable();
            $table->string('mobile_normalized', 30)->nullable();
            $table->string('email', 190)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('gender', 20)->nullable();
            $table->boolean('is_business_customer')->default(false);
            $table->string('company_name', 150)->nullable();
            $table->string('gst_number', 30)->nullable();
            $table->string('status', 30)->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->unique('mobile_normalized', 'customers_mobile_normalized_unique');
            $table->index('email', 'customers_email_idx');
            $table->index('status', 'customers_status_idx');
        });

        Schema::create('merchant_customers', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('merchant_id')
                ->constrained('merchant_profiles')
                ->cascadeOnDelete();
            $table->foreignId('customer_id')
                ->constrained('customers')
                ->cascadeOnDelete();
            $table->string('customer_code', 30);
            $table->text('notes')->nullable();
            $table->string('trust_status', 30)->default('normal')->index();
            $table->string('status', 30)->default('active');
            $table->timestamp('linked_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['merchant_id', 'customer_code'], 'merchant_customers_merchant_code_unique');
            $table->unique(['merchant_id', 'customer_id'], 'merchant_customers_merchant_customer_unique');
            $table->index(['merchant_id', 'status'], 'merchant_customers_merchant_status_idx');
        });

        Schema::create('customer_addresses', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('customer_id')
                ->constrained('customers')
                ->cascadeOnDelete();
            $table->string('label', 80);
            $table->string('recipient_name', 150);
            $table->string('recipient_mobile_country_code', 10)->nullable();
            $table->string('recipient_mobile', 30);
            $table->string('recipient_mobile_normalized', 30);
            $table->string('address_line_1', 190);
            $table->string('address_line_2', 190)->nullable();
            $table->string('landmark', 150)->nullable();
            $table->unsignedMediumInteger('country_id')->nullable();
            $table->unsignedMediumInteger('state_id')->nullable();
            $table->unsignedMediumInteger('city_id')->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->boolean('is_default_shipping')->default(false);
            $table->boolean('is_default_billing')->default(false);
            $table->string('status', 30)->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['customer_id', 'status'], 'customer_addresses_customer_status_idx');
            $table->index(['customer_id', 'is_default_shipping'], 'customer_addresses_shipping_idx');
            $table->index(['customer_id', 'is_default_billing'], 'customer_addresses_billing_idx');

            $table->foreign('country_id')->references('id')->on('loc_countries')->nullOnDelete();
            $table->foreign('state_id')->references('id')->on('loc_states')->nullOnDelete();
            $table->foreign('city_id')->references('id')->on('loc_cities')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_addresses');
        Schema::dropIfExists('merchant_customers');
        Schema::dropIfExists('customers');
    }
};
