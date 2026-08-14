<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carts', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('customer_id')
                ->nullable()
                ->constrained('merchant_customers')
                ->nullOnDelete();
            $table->string('session_token', 100)->nullable();
            $table->timestamps();

            $table->index('customer_id', 'carts_customer_idx');
            $table->index('session_token', 'carts_session_token_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carts');
    }
};
