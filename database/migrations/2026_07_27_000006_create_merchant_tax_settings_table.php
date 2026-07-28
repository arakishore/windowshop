<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchant_tax_settings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->comment('Public identifier exposed in URLs and forms');
            $table->foreignId('merchant_id')->constrained('merchant_profiles')->cascadeOnDelete();
            $table->boolean('tax_enabled')->default(false)->comment('Whether this merchant wants tax fields and tax logic enabled');
            $table->foreignId('default_tax_class_id')->nullable()->constrained('tax_classes')->restrictOnDelete();
            $table->boolean('prices_include_tax')->default(true)->comment('Whether merchant-entered prices are inclusive of tax');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique('merchant_id', 'merchant_tax_settings_merchant_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_tax_settings');
    }
};
