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
        Schema::create('auth_mobile_verification_tokens', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')
                ->nullable()
                ->unique()
                ->comment('Public identifier exposed in URLs and APIs');
            $table->foreignId('user_id')
                ->index()
                ->constrained('users')
                ->cascadeOnDelete();
            $table->string('mobile', 20)->index();
            $table->string('otp', 255)
                ->nullable()
                ->comment('Hashed OTP');
            $table->string('token')
                ->nullable()
                ->comment('Hashed verification token if token-based verification is used')
                ->index();
            $table->string('status', 30)
                ->default('pending')
                ->comment('pending,verified,expired,revoked')
                ->index();
            $table->string('requested_ip', 45)
                ->nullable()
                ->comment('IPv4 or IPv6 address');
            $table->text('user_agent')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
            $table->index(['mobile', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auth_mobile_verification_tokens');
    }
};
