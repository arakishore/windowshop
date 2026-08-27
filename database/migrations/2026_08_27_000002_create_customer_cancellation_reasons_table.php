<?php

use App\Models\CustomerCancellationReason;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_cancellation_reasons', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('code', 80)->unique();
            $table->string('name', 150);
            $table->boolean('requires_comment')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('status', 30)->default('active')->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'sort_order', 'name'], 'customer_cancel_reasons_status_sort_idx');
        });

        foreach (CustomerCancellationReason::defaults() as $code => $reason) {
            CustomerCancellationReason::query()->create([
                'code' => $code,
                ...$reason,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_cancellation_reasons');
    }
};
