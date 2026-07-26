<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchant_return_reasons', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('merchant_id')->constrained('merchant_profiles')->cascadeOnDelete();
            $table->string('code', 80);
            $table->string('name', 120);
            $table->unsignedInteger('sort_order')->default(99);
            $table->boolean('restock_by_default')->default(true);
            $table->boolean('requires_manager_override')->default(false);
            $table->string('status', 30)->default('active')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['merchant_id', 'code'], 'merchant_return_reasons_merchant_code_unique');
            $table->index(['merchant_id', 'status', 'sort_order'], 'merchant_return_reasons_merchant_status_sort_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_return_reasons');
    }
};
