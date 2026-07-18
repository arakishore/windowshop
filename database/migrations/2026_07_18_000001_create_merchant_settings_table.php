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
        Schema::create('merchant_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')
                ->constrained('merchant_profiles')
                ->cascadeOnDelete();
            $table->string('group', 50)->index();
            $table->string('setting_key');
            $table->longText('setting_value')->nullable();
            $table->string('setting_type', 30)->default('string');
            $table->timestamps();

            $table->unique(['merchant_id', 'group', 'setting_key'], 'merchant_settings_merchant_group_key_unique');
            $table->index(['merchant_id', 'group'], 'merchant_settings_merchant_group_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('merchant_settings');
    }
};
