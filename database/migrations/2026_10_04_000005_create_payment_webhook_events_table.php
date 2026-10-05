<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payment_webhook_events')) {
            if (! Schema::hasIndex('payment_webhook_events', 'pwe_payment_order_idx')) {
                Schema::table('payment_webhook_events', function (Blueprint $table): void {
                    $table->index(['provider_payment_id', 'provider_order_id'], 'pwe_payment_order_idx');
                });
            }

            return;
        }

        Schema::create('payment_webhook_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_account_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 50);
            $table->string('provider_event_id', 191);
            $table->string('event_type', 100);
            $table->string('provider_payment_id')->nullable();
            $table->string('provider_order_id')->nullable();
            $table->unsignedBigInteger('amount_minor')->nullable();
            $table->char('currency', 3)->nullable();
            $table->string('disposition', 50);
            $table->string('failure_reason')->nullable();
            $table->char('payload_hash', 64);
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->unique(['payment_account_id', 'provider', 'provider_event_id'], 'payment_webhook_event_identity');
            $table->index(['provider_payment_id', 'provider_order_id'], 'pwe_payment_order_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_webhook_events');
    }
};
