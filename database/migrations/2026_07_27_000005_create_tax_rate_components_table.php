<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_rate_components', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->foreignId('tax_rate_id')
                ->constrained('tax_rates')
                ->restrictOnDelete();
            $table->string('code', 50)
                ->comment('Stable component code such as VAT, STATE, CITY, or a country-specific code');
            $table->string('name');
            $table->decimal('rate', 8, 4)
                ->comment('Component tax percentage rate');
            $table->string('jurisdiction_type', 50)
                ->nullable()
                ->comment('Optional jurisdiction level such as country, state, county, city');
            $table->unsignedInteger('priority')
                ->default(0)
                ->comment('Display/calculation ordering for components');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tax_rate_id', 'code'], 'tax_rate_components_rate_code_unique');
            $table->index(['tax_rate_id', 'priority'], 'tax_rate_components_rate_priority_idx');
            $table->index('jurisdiction_type', 'tax_rate_components_jurisdiction_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_rate_components');
    }
};
