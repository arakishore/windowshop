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
        Schema::create('merchant_verifications', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')
                ->unique()
                ->comment('Public identifier exposed in URLs and APIs');
            $table->foreignId('merchant_id');
            $table->string('verification_type', 50)
                ->default('profile')
                ->comment('profile,document,bank,address');
            $table->string('related_type')->nullable();
            $table->unsignedBigInteger('related_id')->nullable();
            $table->string('old_status', 30)->nullable();
            $table->string('new_status', 30)->index();
            $table->text('admin_comment')
                ->nullable()
                ->comment('Internal reviewer comments. Never visible to merchants or customers.');
            $table->foreignId('reviewed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->index();
            $table->timestamps();

            $table->index(['merchant_id', 'verification_type']);
            $table->index(['related_type', 'related_id']);
            $table->foreign('merchant_id')
                ->references('id')
                ->on('merchant_profiles')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('merchant_verifications');
    }
};
