<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration { public function up(): void { Schema::create('payment_accounts', function(Blueprint $table){ $table->id(); $table->foreignId('merchant_id')->constrained('merchant_profiles')->cascadeOnDelete(); $table->string('provider',50); $table->string('name'); $table->string('mode',20); $table->boolean('enabled')->default(false); $table->string('public_key')->nullable(); $table->text('secret')->nullable(); $table->text('webhook_secret')->nullable(); $table->string('provider_account_reference')->nullable(); $table->timestamps(); $table->index(['merchant_id','provider','mode']); }); }
 public function down(): void { Schema::dropIfExists('payment_accounts'); } };
