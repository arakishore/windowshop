<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_delivery_logs', function (Blueprint $table): void {
            $table->string('delivery_key', 64)->nullable()->unique()->after('uuid');
        });
    }

    public function down(): void
    {
        Schema::table('notification_delivery_logs', function (Blueprint $table): void {
            $table->dropUnique(['delivery_key']);
            $table->dropColumn('delivery_key');
        });
    }
};
