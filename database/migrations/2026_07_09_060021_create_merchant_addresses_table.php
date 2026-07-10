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
            $table->uuid('uuid')
                ->unique()
                ->comment('Public identifier exposed in URLs and APIs');
            $table->foreignId('merchant_id');
            $table->string('address_type', 30)
                ->default('business')
                ->comment('business,billing,pickup,return,warehouse');
            $table->string('contact_name', 150)->nullable();
            $table->string('contact_mobile', 20)->nullable();
            $table->string('address_line_1');
            $table->string('address_line_2')->nullable();
            $table->string('landmark', 150)->nullable();
            $table->unsignedMediumInteger('city_id')->nullable();
            $table->unsignedMediumInteger('state_id')->nullable();
            $table->unsignedMediumInteger('country_id')->nullable();
            $table->string('pincode', 20)->nullable()->index();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('is_default')->default(false);
            $table->string('status', 30)
                ->default('active')
                ->comment('active,inactive,deleted')
                ->index();
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('deleted_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['merchant_id', 'address_type']);
            $table->index(['merchant_id', 'is_default']);
            $table->foreign('merchant_id')
                ->references('id')
                ->on('merchant_profiles')
                ->cascadeOnDelete();
            $table->foreign('city_id')
                ->references('id')
                ->on('loc_cities')
                ->nullOnDelete();
            $table->foreign('state_id')
                ->references('id')
                ->on('loc_states')
                ->nullOnDelete();
            $table->foreign('country_id')
                ->references('id')
                ->on('loc_countries')
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
