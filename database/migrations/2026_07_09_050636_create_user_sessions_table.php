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
        Schema::create('user_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->index()->constrained()->cascadeOnDelete();
            $table->string('session_id')->index();
            $table->string('guard', 30)
                ->comment('admin,merchant,customer');
            $table->string('login_method', 30)
                ->default('password')
                ->comment('password,otp,google,facebook,apple');
            $table->dateTime('login_at');
            $table->dateTime('last_seen_at')->nullable()->index();
            $table->dateTime('logout_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('country', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('browser', 100)->nullable();
            $table->string('browser_version', 30)->nullable();
            $table->string('platform', 100)->nullable();
            $table->string('device_type', 50)
                ->nullable()
                ->comment('desktop,mobile,tablet,bot,unknown');
            $table->text('user_agent')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('remember_me')->default(false);
            $table->string('logout_reason', 50)
                ->nullable()
                ->comment('manual,timeout,force_logout,password_changed');
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_sessions');
    }
};
