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
        Schema::create('product_images', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();
            $table->foreignId('product_variant_id')
                ->nullable()
                ->constrained('product_variants')
                ->cascadeOnDelete();
            $table->string('image_path', 500);
            $table->string('thumbnail_path', 500)->nullable();
            $table->string('title')->nullable();
            $table->string('alt_text')->nullable();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->boolean('is_primary')->default(false)->index();
            $table->string('status', 30)
                ->default('active')
                ->comment('active,inactive')
                ->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['product_id', 'product_variant_id'], 'product_images_product_variant_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_images');
    }
};
