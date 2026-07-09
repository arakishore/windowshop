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
        Schema::create('loc_countries', function (Blueprint $table) {
            $table->mediumIncrements('id');
            $table->uuid('uuid')
                ->nullable()
                ->unique()
                ->comment('Public identifier exposed in URLs and APIs');
            $table->string('name', 100)->index();
            $table->char('iso3', 3)->nullable()->unique();
            $table->char('numeric_code', 3)->nullable()->unique();
            $table->char('iso2', 2)->nullable()->unique();
            $table->string('phonecode')
                ->nullable()
                ->comment('International calling code');
            $table->string('capital')->nullable();
            $table->string('currency')
                ->nullable()
                ->comment('ISO 4217 currency code');
            $table->string('currency_name')->nullable();
            $table->string('currency_symbol')->nullable();
            $table->unsignedMediumInteger('region_id')->nullable();
            $table->unsignedMediumInteger('subregion_id')->nullable();
            $table->string('nationality')->nullable();
            $table->boolean('status')
                ->default(true)
                ->comment('1=active,0=inactive')
                ->index();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('region_id')
                ->references('id')
                ->on('loc_regions')
                ->restrictOnDelete();
            $table->foreign('subregion_id')
                ->references('id')
                ->on('loc_subregions')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loc_countries');
    }
};
