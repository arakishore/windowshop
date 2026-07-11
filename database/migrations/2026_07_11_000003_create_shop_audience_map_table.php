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
        Schema::create('shop_audience_map', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->foreignId('audience_id')->constrained('shop_audiences')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['shop_id', 'audience_id']);
            $table->index(['audience_id', 'shop_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shop_audience_map');
    }
};
