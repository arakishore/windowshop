<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_rates', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->uuid('uuid')
                ->unique()
                ->comment('Public identifier exposed in URLs and APIs');
            $table->foreignId('tax_class_id')
                ->constrained('tax_classes')
                ->restrictOnDelete();
            $table->string('name');
            $table->decimal('total_rate', 8, 4)
                ->comment('Total tax percentage rate, for example 2.5000, 7.2500, 20.0000');
            $table->date('effective_from')
                ->comment('First date this rate can be applied');
            $table->date('effective_to')
                ->nullable()
                ->comment('Last date this rate can be applied; null means open-ended');
            $table->unsignedInteger('priority')
                ->default(0)
                ->comment('Deterministic ordering for tax-rate resolution or display; overlapping active periods are rejected by the service layer');
            $table->string('status', 30)
                ->default('active')
                ->comment('active,inactive,deleted')
                ->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(
                ['tax_class_id', 'status', 'effective_from', 'effective_to', 'priority'],
                'tax_rates_resolve_idx'
            );
            $table->index(['tax_class_id', 'effective_from'], 'tax_rates_class_from_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_rates');
    }
};
