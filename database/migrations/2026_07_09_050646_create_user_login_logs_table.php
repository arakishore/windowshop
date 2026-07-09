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
        Schema::create('user_login_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->index()->constrained()->nullOnDelete();
            $table->string('guard', 30)
                ->nullable()
                ->comment('admin,merchant,customer');
            $table->string('email')->nullable();
            $table->string('mobile', 20)->nullable();
            $table->string('login_method', 30)
                ->nullable()
                ->comment('password,otp,google,facebook,apple');
            $table->enum('status', ['success', 'failed'])
                ->charset('utf8mb4')
                ->collation('utf8mb4_unicode_ci')
                ->comment('success,failed')
                ->index();
            $table->string('failure_reason')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('country', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('browser', 100)->nullable();
            $table->string('platform', 100)->nullable();
            $table->string('device_type', 50)
                ->nullable()
                ->comment('desktop,mobile,tablet,bot,unknown');
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_login_logs');
    }
};
