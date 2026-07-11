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
        Schema::table('shops', function (Blueprint $table) {
            if (Schema::hasColumn('shops', 'verified_by')) {
                $table->dropConstrainedForeignId('verified_by');
            }

            foreach (['verification_status', 'verified_at', 'rejection_reason'] as $column) {
                if (Schema::hasColumn('shops', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            if (! Schema::hasColumn('shops', 'verification_status')) {
                $table->string('verification_status', 30)
                    ->default('pending')
                    ->comment('pending,approved,rejected,suspended')
                    ->index();
            }

            if (! Schema::hasColumn('shops', 'verified_at')) {
                $table->timestamp('verified_at')->nullable();
            }

            if (! Schema::hasColumn('shops', 'verified_by')) {
                $table->foreignId('verified_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('shops', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable();
            }
        });
    }
};
