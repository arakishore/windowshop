<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchant_customer_addresses', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('merchant_customer_id')
                ->constrained('merchant_customers')
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
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['merchant_customer_id', 'status'], 'merchant_customer_addresses_customer_status_idx');
            $table->index(['merchant_customer_id', 'is_default_shipping'], 'merchant_customer_addresses_shipping_idx');
            $table->index(['merchant_customer_id', 'is_default_billing'], 'merchant_customer_addresses_billing_idx');

            $table->foreign('country_id')->references('id')->on('loc_countries')->nullOnDelete();
            $table->foreign('state_id')->references('id')->on('loc_states')->nullOnDelete();
            $table->foreign('city_id')->references('id')->on('loc_cities')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_customer_addresses');
    }
};
