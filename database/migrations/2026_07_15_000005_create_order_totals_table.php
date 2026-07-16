<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_totals', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('code', 100);
            $table->string('title');
            $table->decimal('amount', 14, 2);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('source', 100)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'sort_order'], 'order_totals_order_sort_idx');
            $table->index(['order_id', 'code'], 'order_totals_order_code_idx');
            $table->index('source', 'order_totals_source_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_totals');
    }
};
