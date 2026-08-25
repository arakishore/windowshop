<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->text('customer_order_note')->nullable()->after('remarks');
        });

        Schema::create('order_comments', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('author_type', 30)->index();
            $table->text('comment');
            $table->string('visibility', 30)->index();
            $table->boolean('notify_customer')->default(false);
            $table->boolean('notify_email')->default(false);
            $table->boolean('notify_sms')->default(false);
            $table->boolean('notify_whatsapp')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['order_id', 'created_at'], 'order_comments_order_created_idx');
            $table->index(['order_id', 'visibility'], 'order_comments_order_visibility_idx');
            $table->index('deleted_at', 'order_comments_deleted_at_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_comments');

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn('customer_order_note');
        });
    }
};
