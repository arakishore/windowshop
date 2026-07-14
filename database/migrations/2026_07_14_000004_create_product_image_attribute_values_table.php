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
        Schema::create('product_image_attribute_values', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_image_id');
            $table->foreignId('product_attribute_group_value_id');
            $table->timestamps();

            $table->foreign('product_image_id', 'piav_image_fk')
                ->references('id')
                ->on('product_images')
                ->cascadeOnDelete();
            $table->foreign('product_attribute_group_value_id', 'piav_group_value_fk')
                ->references('id')
                ->on('product_attribute_group_values')
                ->restrictOnDelete();

            $table->unique(
                ['product_image_id', 'product_attribute_group_value_id'],
                'product_image_attribute_value_unique',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_image_attribute_values');
    }
};
