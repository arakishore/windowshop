<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_pages', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->enum('page_type', ['standard', 'custom']);
            $table->string('page_key', 40)->nullable();
            $table->string('title', 180);
            $table->string('slug', 180);
            $table->longText('body')->nullable()->comment('Plain text; policies body contains additional merchant notes only.');
            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['shop_id', 'page_key'], 'shop_pages_shop_key_unique');
            $table->unique(['shop_id', 'slug'], 'shop_pages_shop_slug_unique');
            $table->index(['shop_id', 'status'], 'shop_pages_shop_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_pages');
    }
};
