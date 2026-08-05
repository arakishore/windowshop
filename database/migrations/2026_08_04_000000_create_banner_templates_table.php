<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banner_templates', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('code', 100)->unique();
            $table->string('category', 40)->index();
            $table->string('name', 180);
            $table->text('description')->nullable();
            $table->string('default_title', 180);
            $table->string('default_subtitle')->nullable();
            $table->string('default_button_text', 80)->nullable();
            $table->string('desktop_image_path');
            $table->string('mobile_image_path')->nullable();
            $table->string('default_position', 80)->default('store_hero')->index();
            $table->string('availability', 30)->default('both')->index();
            $table->string('event_code', 80)->nullable()->index();
            $table->smallInteger('start_offset_days')->nullable();
            $table->smallInteger('end_offset_days')->nullable();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->string('status', 30)->default('active')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['category', 'status', 'sort_order'], 'banner_templates_category_status_sort_idx');
            $table->index(['availability', 'status'], 'banner_templates_availability_status_idx');
            $table->index(['event_code', 'status'], 'banner_templates_event_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banner_templates');
    }
};
