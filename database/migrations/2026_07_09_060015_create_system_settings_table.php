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
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')
                ->nullable()
                ->unique()
                ->comment('Public identifier exposed in URLs and APIs');
            $table->foreignId('group_id')
                ->nullable()
                ->index()
                ->constrained('system_setting_groups')
                ->nullOnDelete();
            $table->string('key', 150)->unique();
            $table->string('label', 150)->nullable();
            $table->longText('value')->nullable();
            $table->string('value_type', 30)
                ->default('string')
                ->comment('string,integer,boolean,json,array,text,encrypted');
            $table->boolean('is_public')
                ->default(false)
                ->comment('Can be exposed to frontend/API')
                ->index();
            $table->boolean('is_encrypted')
                ->default(false)
                ->comment('Sensitive value stored encrypted');
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('status', 30)
                ->default('active')
                ->comment('active,inactive,deleted')
                ->index();
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('deleted_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
