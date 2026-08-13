<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('postal_codes', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->char('source_key', 40)->unique();
            $table->string('circle_name', 120)->nullable()->index();
            $table->string('region_name', 120)->nullable()->index();
            $table->string('division_name', 120)->nullable()->index();
            $table->string('office_name', 180)->index();
            $table->string('postal_code', 20)->index();
            $table->string('office_type', 20)->nullable()->index();
            $table->string('delivery_status', 40)->nullable()->index();
            $table->boolean('shipping_enabled')->default(false)->index();
            $table->string('district', 120)->nullable()->index();
            $table->string('state', 120)->nullable()->index();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active')->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['postal_code', 'office_name', 'district', 'state'], 'postal_codes_location_lookup_idx');
            $table->index(['state', 'district', 'status'], 'postal_codes_state_district_status_idx');
            $table->index(['postal_code', 'status', 'shipping_enabled'], 'postal_codes_postal_status_shipping_idx');
            $table->index('deleted_at', 'postal_codes_deleted_at_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('postal_codes');
    }
};
