<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_categories', function (Blueprint $table): void {
            $table->foreignId('default_tax_class_id')
                ->nullable()
                ->after('sort_order')
                ->constrained('tax_classes')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('product_categories', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('default_tax_class_id');
        });
    }
};
