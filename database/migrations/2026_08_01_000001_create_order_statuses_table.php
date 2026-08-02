<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_statuses', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('code', 100)->unique();
            $table->string('name', 150);
            $table->string('customer_label', 150)->nullable();
            $table->string('description', 500)->nullable();
            $table->text('internal_notes')->nullable();
            $table->string('category', 50)->index();
            $table->string('badge_type', 30)->default('secondary');
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->boolean('is_system')->default(true)->index();
            $table->boolean('is_terminal')->default(false)->index();
            $table->boolean('customer_visible')->default(true);
            $table->boolean('merchant_visible')->default(true);
            $table->string('status', 20)->default('active')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'category', 'sort_order'], 'order_statuses_status_category_sort_idx');
            $table->index('deleted_at', 'order_statuses_deleted_at_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_statuses');
    }
};
