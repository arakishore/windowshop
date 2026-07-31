<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->boolean('tax_enabled')
                ->default(false)
                ->after('line_total');
            $table->string('tax_resolution_source', 100)
                ->nullable()
                ->after('tax_enabled');
            $table->unsignedBigInteger('tax_class_id')
                ->nullable()
                ->after('tax_resolution_source')
                ->comment('Historical traceability ID only; no foreign key to tax_classes');
            $table->string('tax_class_code', 50)
                ->nullable()
                ->after('tax_class_id');
            $table->string('tax_class_name')
                ->nullable()
                ->after('tax_class_code');
            $table->unsignedBigInteger('tax_rate_id')
                ->nullable()
                ->after('tax_class_name')
                ->comment('Historical traceability ID only; no foreign key to tax_rates');
            $table->string('tax_rate_name')
                ->nullable()
                ->after('tax_rate_id');
            $table->decimal('tax_rate', 8, 4)
                ->nullable()
                ->after('tax_rate_name')
                ->comment('Resolved total rate from EffectiveTaxRateResult.totalRate');
            $table->string('price_mode', 30)
                ->nullable()
                ->after('tax_rate');
            $table->decimal('taxable_amount', 14, 2)
                ->nullable()
                ->after('price_mode');
        });

        Schema::create('order_item_tax_components', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->foreignId('order_item_id')
                ->constrained('order_items')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('tax_component_id')
                ->nullable()
                ->comment('Historical traceability ID only; no foreign key to tax_rate_components');
            $table->string('component_code', 50);
            $table->string('component_name');
            $table->string('jurisdiction_type', 50)->nullable();
            $table->decimal('rate', 8, 4);
            $table->decimal('amount', 14, 2);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('component_code', 'order_item_tax_components_code_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_item_tax_components');

        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropColumn([
                'tax_enabled',
                'tax_resolution_source',
                'tax_class_id',
                'tax_class_code',
                'tax_class_name',
                'tax_rate_id',
                'tax_rate_name',
                'tax_rate',
                'price_mode',
                'taxable_amount',
            ]);
        });
    }
};
