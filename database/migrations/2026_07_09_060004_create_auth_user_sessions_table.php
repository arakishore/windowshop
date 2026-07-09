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
        Schema::create('auth_user_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')
                ->nullable()
                ->unique()
                ->comment('Public identifier exposed in URLs and APIs');
            $table->foreignId('user_id')
                ->index()
                ->constrained('users')
                ->cascadeOnDelete();
            $table->string('session_id')
                ->nullable()
                ->comment('Laravel session ID if available')
                ->index();
            $table->string('guard_name', 50)
                ->nullable()
                ->comment('Auth guard used for login');
            $table->string('device_name', 150)
                ->nullable()
                ->comment('Readable device/browser name');
            $table->string('device_type', 50)
                ->nullable()
                ->comment('desktop,mobile,tablet,bot,unknown');
            $table->string('browser', 100)->nullable();
            $table->string('browser_version', 50)->nullable();
            $table->string('platform', 100)
                ->nullable()
                ->comment('Operating system/platform');
            $table->string('platform_version', 50)->nullable();
            $table->string('ip_address', 45)
                ->nullable()
                ->comment('IPv4 or IPv6 address');
            $table->text('user_agent')->nullable();
            $table->timestamp('login_at')->nullable();
            $table->timestamp('last_activity_at')->nullable()->index();
            $table->timestamp('logout_at')->nullable();
            $table->boolean('is_current')
                ->default(false)
                ->comment('Current session for this login request');
            $table->boolean('is_active')
                ->default(true)
                ->comment('Whether session is currently active')
                ->index();
            $table->string('logout_reason', 50)
                ->nullable()
                ->comment('manual,expired,forced,password_changed,admin_terminated');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'is_active']);
            $table->index(['user_id', 'last_activity_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auth_user_sessions');
    }
};
