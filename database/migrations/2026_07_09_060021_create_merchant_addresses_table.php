<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('merchant_addresses', function (Blueprint $table) {

            $table->id();

            $table->uuid('uuid')->unique();

            $table->foreignId('merchant_id');

            // Keep for future scalability, but UI will always use 'business' in V1
            $table->string('address_type', 30)
                ->default('business')
                ->comment('business,billing,pickup,return,warehouse');

            $table->string('address_line_1');

            $table->string('address_line_2')->nullable();

            $table->string('landmark', 150)->nullable();

            $table->unsignedMediumInteger('country_id')->nullable();

            $table->unsignedMediumInteger('state_id')->nullable();

            $table->unsignedMediumInteger('city_id')->nullable();

            $table->string('pincode', 20)->nullable()->index();

            // Audit
            $table->string('status', 30)
                ->default('active')
                ->comment('active,inactive,deleted')
                ->index();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['merchant_id', 'address_type']);

            $table->foreign('merchant_id')
                ->references('id')
                ->on('merchant_profiles')
                ->cascadeOnDelete();

            $table->foreign('country_id')
                ->references('id')
                ->on('loc_countries')
                ->nullOnDelete();

            $table->foreign('state_id')
                ->references('id')
                ->on('loc_states')
                ->nullOnDelete();

            $table->foreign('city_id')
                ->references('id')
                ->on('loc_cities')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('merchant_addresses');
    }
};
