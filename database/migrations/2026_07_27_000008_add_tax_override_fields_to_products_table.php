<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->enum('tax_mode', ['inherit', 'override', 'exempt'])
                ->default('inherit')
                ->after('brand_id')
                ->index();
            $table->foreignId('tax_class_id')
                ->nullable()
                ->after('tax_mode')
                ->constrained('tax_classes')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('tax_class_id');
            $table->dropColumn('tax_mode');
        });
    }
};
