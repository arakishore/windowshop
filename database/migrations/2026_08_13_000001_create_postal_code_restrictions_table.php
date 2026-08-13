<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('postal_code_restrictions', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->string('postal_code', 20)->index();
            $table->foreignId('merchant_id')->nullable()->constrained('merchant_profiles')->restrictOnDelete();
            $table->foreignId('shop_id')->nullable()->constrained('shops')->restrictOnDelete();
            $table->text('reason')->nullable();
            $table->timestamp('starts_at')->nullable()->index();
            $table->timestamp('ends_at')->nullable()->index();
            $table->enum('status', ['active', 'inactive'])->default('active')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['postal_code', 'merchant_id', 'shop_id', 'status'], 'postal_restrictions_scope_status_idx');
            $table->index(['merchant_id', 'shop_id', 'status'], 'postal_restrictions_shop_status_idx');
            $table->index('deleted_at', 'postal_restrictions_deleted_at_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('postal_code_restrictions');
    }
};
