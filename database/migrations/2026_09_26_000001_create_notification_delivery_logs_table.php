<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_delivery_logs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('notification_key', 150);
            $table->string('recipient_type', 50);
            $table->unsignedBigInteger('recipient_id')->nullable();
            $table->foreignId('shop_id')->nullable()->constrained('shops')->nullOnDelete();
            $table->foreignId('merchant_id')->nullable()->constrained('merchant_profiles')->nullOnDelete();
            $table->string('related_type', 100)->nullable();
            $table->string('related_id', 191)->nullable();
            $table->string('channel', 20);
            $table->string('destination', 255)->nullable();
            $table->string('provider_mode', 30)->nullable();
            $table->string('provider', 100)->nullable();
            $table->string('status', 30);
            $table->string('error_summary', 1000)->nullable();
            $table->timestamp('attempted_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['merchant_id', 'created_at']);
            $table->index(['shop_id', 'created_at']);
            $table->index(['notification_key', 'created_at']);
            $table->index(['channel', 'status', 'created_at']);
            $table->index(['related_type', 'related_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_delivery_logs');
    }
};
