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
        Schema::create('merchant_profiles', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')
                ->unique()
                ->comment('Public identifier exposed in URLs and APIs');
            $table->foreignId('user_id');
            $table->string('business_name', 150)->index();
            $table->string('legal_name', 150)->nullable();
            $table->string('business_type', 50)
                ->nullable()
                ->comment('individual,proprietorship,partnership,llp,pvt_ltd,public_ltd,other');
            $table->string('gst_number', 30)->nullable()->unique();
            $table->boolean('has_shop_license')->nullable();
            $table->boolean('has_fssai')->nullable();
            $table->string('contact_person_name', 150)->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_mobile', 20)->nullable();
            $table->string('alternate_mobile', 20)->nullable();
            $table->string('verification_status', 30)
                ->default('pending')
                ->comment('pending,submitted,approved,rejected,suspended')
                ->index();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->text('rejection_reason')->nullable();
            $table->string('status', 30)
                ->default('active')
                ->comment('active,inactive,suspended,deleted')
                ->index();
            $table->text('admin_note')
                ->nullable()
                ->comment('Internal administrative notes. Never visible to merchants or customers.');
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

            $table->unique('user_id');
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('merchant_profiles');
    }
};
