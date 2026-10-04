<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_accounts', function (Blueprint $table): void {
            $table->string('webhook_token', 64)->nullable()->unique()->after('webhook_secret');
        });
        DB::table('payment_accounts')->whereNull('webhook_token')->orderBy('id')->eachById(function (object $account): void {
            DB::table('payment_accounts')->where('id', $account->id)->update(['webhook_token' => Str::random(64)]);
        });
        Schema::table('payment_accounts', function (Blueprint $table): void {
            $table->string('webhook_token', 64)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('payment_accounts', fn (Blueprint $table) => $table->dropColumn('webhook_token'));
    }
};
