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
        Schema::create('shop_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')
                ->constrained('shops')
                ->cascadeOnDelete();
            $table->string('group', 50)->index();
            $table->string('setting_key');
            $table->longText('setting_value')->nullable();
            $table->string('setting_type', 30)->default('string');
            $table->timestamps();

            $table->unique(['shop_id', 'group', 'setting_key'], 'shop_settings_shop_group_key_unique');
            $table->index(['shop_id', 'group'], 'shop_settings_shop_group_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shop_settings');
    }
};
