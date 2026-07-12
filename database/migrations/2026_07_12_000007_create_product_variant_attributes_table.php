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
        Schema::create('product_variant_attributes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_variant_id');
            $table->foreignId('product_attribute_group_id');
            $table->foreignId('product_attribute_group_value_id');
            $table->timestamps();

            $table->foreign('product_variant_id', 'pva_variant_fk')
                ->references('id')
                ->on('product_variants')
                ->cascadeOnDelete();
            $table->foreign('product_attribute_group_id', 'pva_group_fk')
                ->references('id')
                ->on('product_attribute_groups')
                ->restrictOnDelete();
            $table->foreign('product_attribute_group_value_id', 'pva_group_value_fk')
                ->references('id')
                ->on('product_attribute_group_values')
                ->restrictOnDelete();

            $table->unique(
                ['product_variant_id', 'product_attribute_group_id'],
                'product_variant_attribute_group_unique',
            );

            $table->index(
                ['product_attribute_group_id', 'product_attribute_group_value_id'],
                'product_variant_attribute_lookup_idx',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_variant_attributes');
    }
};
