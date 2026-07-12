<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('product_attributes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();
            $table->foreignId('product_attribute_group_id')
                ->constrained('product_attribute_groups')
                ->restrictOnDelete();
            $table->foreignId('product_attribute_group_value_id')
                ->constrained('product_attribute_group_values')
                ->restrictOnDelete();
            $table->timestamps();

            $table->unique(
                ['product_id', 'product_attribute_group_id', 'product_attribute_group_value_id'],
                'product_attribute_unique',
            );

            $table->index(
                ['product_attribute_group_id', 'product_attribute_group_value_id'],
                'product_attribute_lookup_idx',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_attributes');
    }
};
