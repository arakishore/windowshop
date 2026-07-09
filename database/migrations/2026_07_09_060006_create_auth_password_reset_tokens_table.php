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
        Schema::create('auth_password_reset_tokens', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')
                ->nullable()
                ->unique()
                ->comment('Public identifier exposed in URLs and APIs');
            $table->foreignId('user_id')
                ->nullable()
                ->index()
                ->constrained('users')
                ->cascadeOnDelete();
            $table->string('email')
                ->nullable()
                ->comment('Email used for password reset')
                ->index();
            $table->string('mobile', 20)
                ->nullable()
                ->comment('Mobile used for password reset')
                ->index();
            $table->string('token')
                ->nullable()
                ->comment('Hashed reset token')
                ->index();
            $table->string('otp', 255)
                ->nullable()
                ->comment('Hashed OTP if OTP reset is used');
            $table->string('channel', 30)
                ->default('email')
                ->comment('email,mobile,whatsapp');
            $table->string('status', 30)
                ->default('pending')
                ->comment('pending,used,expired,revoked')
                ->index();
            $table->string('requested_ip', 45)
                ->nullable()
                ->comment('IPv4 or IPv6 address');
            $table->text('user_agent')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
            $table->index(['email', 'status']);
            $table->index(['mobile', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auth_password_reset_tokens');
    }
};
