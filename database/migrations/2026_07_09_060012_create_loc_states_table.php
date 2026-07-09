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
        Schema::create('loc_states', function (Blueprint $table) {
            $table->mediumIncrements('id');
            $table->uuid('uuid')
                ->nullable()
                ->unique()
                ->comment('Public identifier exposed in URLs and APIs');
            $table->string('name');
            $table->unsignedMediumInteger('country_id');
            $table->char('country_code', 2)
                ->comment('ISO 3166-1 alpha-2 country code');
            $table->string('iso2')
                ->nullable()
                ->comment('State/province code');
            $table->string('iso3166_2', 10)
                ->nullable()
                ->comment('ISO 3166-2 subdivision code');
            $table->boolean('status')
                ->default(true)
                ->comment('1=active,0=inactive')
                ->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['country_id', 'name']);
            $table->index(['country_id', 'iso2']);
            $table->foreign('country_id')
                ->references('id')
                ->on('loc_countries')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loc_states');
    }
};
