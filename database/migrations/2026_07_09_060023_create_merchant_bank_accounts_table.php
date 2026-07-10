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
        Schema::create('merchant_bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')
                ->unique()
                ->comment('Public identifier exposed in URLs and APIs');
            $table->foreignId('merchant_id');
            $table->string('account_holder_name', 150);
            $table->string('bank_name', 150)->nullable();
            $table->string('branch_name', 150)->nullable();
            $table->string('account_number')
                ->comment('Must be encrypted through the model or service layer and masked in all output.');
            $table->string('ifsc_code', 20)->nullable()->index();
            $table->string('account_type', 30)
                ->nullable()
                ->comment('savings,current,other');
            $table->string('upi_id', 100)->nullable();
            $table->string('verification_status', 30)
                ->default('pending')
                ->comment('pending,verified,rejected')
                ->index();
            $table->boolean('is_default')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->text('rejection_reason')->nullable();
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

            $table->index(['merchant_id', 'is_default']);
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
        Schema::dropIfExists('merchant_bank_accounts');
    }
};
