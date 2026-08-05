<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banners', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('merchant_id')->nullable()->constrained('merchant_profiles')->restrictOnDelete();
            $table->foreignId('shop_id')->nullable()->constrained('shops')->restrictOnDelete();
            $table->string('source_type', 30)->default('custom_upload')->index();
            $table->foreignId('banner_template_id')
                ->nullable()
                ->constrained('banner_templates')
                ->nullOnDelete();
            $table->string('position', 80)->index();
            $table->string('title', 180);
            $table->string('subtitle')->nullable();
            $table->text('description')->nullable();
            $table->string('desktop_image_path');
            $table->string('mobile_image_path')->nullable();
            $table->string('link_type', 40)->default('none')->index();
            $table->string('link_value')->nullable();
            $table->boolean('open_in_new_tab')->default(false);
            $table->string('button_text', 80)->nullable();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->dateTime('starts_at')->nullable()->index();
            $table->dateTime('ends_at')->nullable()->index();
            $table->string('status', 30)->default('active')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['position', 'status', 'starts_at', 'ends_at'], 'banners_position_visibility_idx');
            $table->index(['merchant_id', 'shop_id', 'position', 'status'], 'banners_owner_position_status_idx');
            $table->index(['merchant_id', 'shop_id', 'position', 'sort_order'], 'banners_owner_position_sort_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banners');
    }
};
