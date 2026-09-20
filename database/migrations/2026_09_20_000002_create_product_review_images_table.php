<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_review_images', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('product_review_id')->constrained('product_reviews')->cascadeOnDelete();
            $table->string('image_path', 500);
            $table->string('thumbnail_path', 500);
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['product_review_id', 'sort_order'], 'review_images_review_sort_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_review_images');
    }
};
