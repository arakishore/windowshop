<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('direct_merchant_upi_attempts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->unsignedInteger('sequence');
            $table->string('submitted_reference', 100);
            $table->string('status', 20);
            $table->unsignedTinyInteger('active_slot')->nullable()->default(1);
            $table->timestamp('submitted_at');
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('confirmed_reference', 100)->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->unique(['order_id', 'sequence'], 'direct_upi_attempt_order_sequence_unique');
            $table->unique(['order_id', 'active_slot'], 'direct_upi_attempt_one_active_unique');
            $table->index(['order_id', 'status'], 'direct_upi_attempt_order_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('direct_merchant_upi_attempts');
    }
};
