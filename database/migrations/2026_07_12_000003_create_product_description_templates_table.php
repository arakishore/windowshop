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
        Schema::create('product_description_templates', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('product_category_id')
                ->constrained('product_categories')
                ->cascadeOnDelete();
            $table->string('name', 150);
            $table->text('short_description_template');
            $table->longText('description_template');
            $table->string('meta_title_template')->nullable();
            $table->text('meta_description_template')->nullable();
            $table->string('status', 30)
                ->default('active')
                ->comment('active,inactive,deleted')
                ->index();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['product_category_id', 'status'], 'description_templates_category_status_index');
            $table->index(['status', 'sort_order'], 'description_templates_status_sort_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_description_templates');
    }
};
