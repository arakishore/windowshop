<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_classes', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->uuid('uuid')
                ->unique()
                ->comment('Public identifier exposed in URLs and APIs');
            $table->unsignedMediumInteger('country_id')
                ->comment('Country scope referencing loc_countries.id');
            $table->string('code', 50)
                ->comment('Stable tax class code within the country');
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')
                ->default(0)
                ->index();
            $table->string('status', 30)
                ->default('active')
                ->comment('active,inactive,deleted')
                ->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('country_id')
                ->references('id')
                ->on('loc_countries')
                ->restrictOnDelete();

            $table->unique(['country_id', 'code'], 'tax_classes_country_code_unique');
            $table->index(['country_id', 'status'], 'tax_classes_country_status_idx');
            $table->index(['country_id', 'sort_order'], 'tax_classes_country_sort_idx');
            $table->index('code', 'tax_classes_code_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_classes');
    }
};
