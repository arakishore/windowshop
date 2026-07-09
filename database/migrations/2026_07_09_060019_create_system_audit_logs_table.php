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
        Schema::create('system_audit_logs', function (Blueprint $table) {
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
            $table->string('module', 100)
                ->nullable()
                ->comment('Module name, example auth, shop, product')
                ->index();
            $table->string('action', 100)
                ->comment('Action name, example created, updated, deleted, approved')
                ->index();
            $table->string('auditable_type')->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)
                ->nullable()
                ->comment('IPv4 or IPv6 address');
            $table->text('user_agent')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamp('created_at')->nullable()->index();

            $table->index(['auditable_type', 'auditable_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_audit_logs');
    }
};
