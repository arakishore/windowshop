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
        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')
                ->unique()
                ->comment('Public identifier exposed in URLs and APIs');
            $table->foreignId('user_id')
                ->comment('Reference to users table')
                ->unique()
                ->constrained()
                ->cascadeOnDelete();
            $table->string('employee_code', 30)
                ->nullable()
                ->comment('Internal employee code')
                ->index();
            $table->string('first_name', 100);
            $table->string('middle_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->string('photo')->nullable();
            $table->string('designation', 100)->nullable();
            $table->string('department', 100)->nullable();
            $table->string('status', 30)
                ->charset('utf8mb4')
                ->collation('utf8mb4_unicode_ci')
                ->default('active')
                ->comment('active,inactive,suspended,deleted')
                ->index();
            $table->boolean('is_super_admin')
                ->default(false)
                ->comment('0=Admin,1=Super Admin');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admins');
    }
};
