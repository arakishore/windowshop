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
        Schema::create('auth_user_login_history', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')
                ->nullable()
                ->unique()
                ->comment('Public identifier exposed in URLs and APIs');
            $table->foreignId('user_id')
                ->nullable()
                ->index()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('email')->nullable()->index()
                ->comment('Email used during login attempt');
            $table->string('mobile', 20)->nullable()->index()
                ->comment('Mobile used during login attempt');
            $table->string('guard_name', 50)->nullable();
            $table->string('login_identifier')
                ->nullable()
                ->comment('Submitted username/email/mobile');
            $table->string('status', 50)
                ->default('success')
                ->comment('success,failed,blocked,logout')
                ->index();
            $table->string('failure_reason', 150)
                ->nullable()
                ->comment('invalid_credentials,inactive_user,suspended_user,deleted_user,too_many_attempts,otp_failed,password_expired');
            $table->string('session_id')->nullable()->index();
            $table->string('device_name', 150)->nullable();
            $table->string('device_type', 50)
                ->nullable()
                ->comment('desktop,mobile,tablet,bot,unknown');
            $table->string('browser', 100)->nullable();
            $table->string('browser_version', 50)->nullable();
            $table->string('platform', 100)->nullable();
            $table->string('platform_version', 50)->nullable();
            $table->string('ip_address', 45)
                ->nullable()
                ->comment('IPv4 or IPv6 address');
            $table->text('user_agent')->nullable();
            $table->timestamp('attempted_at')->nullable()->index();
            $table->timestamp('logout_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'attempted_at']);
            $table->index(['status', 'attempted_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auth_user_login_history');
    }
};
