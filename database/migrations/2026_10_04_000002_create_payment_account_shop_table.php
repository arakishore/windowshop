<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration { public function up(): void { Schema::create('payment_account_shop', function(Blueprint $table){ $table->id(); $table->foreignId('payment_account_id')->constrained()->cascadeOnDelete(); $table->foreignId('shop_id')->constrained()->cascadeOnDelete(); $table->timestamps(); $table->unique(['payment_account_id','shop_id']); $table->index('shop_id'); }); }
 public function down(): void { Schema::dropIfExists('payment_account_shop'); } };
