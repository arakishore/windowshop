<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promotion_redemptions', function (Blueprint $table): void {
            $table->unique(
                ['order_id', 'promotion_id', 'promotion_coupon_id'],
                'promotion_redemptions_coupon_order_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('promotion_redemptions', function (Blueprint $table): void {
            $table->dropUnique('promotion_redemptions_coupon_order_unique');
        });
    }
};
