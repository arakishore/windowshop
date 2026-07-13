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
        Schema::create('shops', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->uuid('uuid')
                ->unique()
                ->comment('Public identifier used in admin URLs and APIs');
            $table->foreignId('merchant_id')
                ->constrained('merchant_profiles')
                ->cascadeOnDelete();
            $table->foreignId('root_product_category_id')
                ->constrained('product_categories')
                ->restrictOnDelete();
            $table->string('name', 150)->index();
            $table->string('slug', 180)->unique();
            $table->string('short_description')->nullable();
            $table->text('description')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('banner_path')->nullable();
            $table->string('email')->nullable();
            $table->string('mobile', 20)->nullable()->index();
            $table->string('whatsapp_number', 20)->nullable();
            $table->string('website_url')->nullable();
            $table->string('address_line_1');
            $table->string('address_line_2')->nullable();
            $table->string('landmark', 150)->nullable();
            $table->unsignedMediumInteger('country_id')->nullable();
            $table->unsignedMediumInteger('state_id')->nullable()->index();
            $table->unsignedMediumInteger('city_id')->nullable()->index();
            $table->string('pincode', 20)->nullable()->index();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('status', 30)
                ->default('active')
                ->comment('active,inactive,suspended,deleted')
                ->index();
            $table->text('admin_note')
                ->nullable()
                ->comment('Internal administrative note. Never customer-facing.');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['merchant_id', 'status']);
            $table->index(['root_product_category_id', 'status']);
            $table->index(['city_id', 'status']);
            $table->index(['merchant_id', 'created_at']);

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
        Schema::dropIfExists('shops');
    }
};
