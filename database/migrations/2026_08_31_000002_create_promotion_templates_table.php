<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotion_templates', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->string('code', 80)->unique();
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->string('example')->nullable();
            $table->text('help_text')->nullable();
            $table->string('reward_type', 80);
            $table->json('required_fields')->nullable();
            $table->json('configurable_fields')->nullable();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->string('status', 30)->default('active')->index();
            $table->timestamps();

            $table->index(['status', 'sort_order'], 'promotion_templates_status_sort_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_templates');
    }
};
