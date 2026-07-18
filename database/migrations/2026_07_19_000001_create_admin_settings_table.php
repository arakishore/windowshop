<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('group', 80)->index();
            $table->string('setting_key')->index();
            $table->longText('setting_value')->nullable();
            $table->string('setting_type', 30)->default('string');
            $table->timestamps();

            $table->unique(['group', 'setting_key'], 'admin_settings_group_key_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_settings');
    }
};
