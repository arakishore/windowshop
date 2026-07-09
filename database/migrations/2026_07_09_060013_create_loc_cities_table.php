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
        Schema::create('loc_cities', function (Blueprint $table) {
            $table->mediumIncrements('id');
            $table->uuid('uuid')
                ->nullable()
                ->unique()
                ->comment('Public identifier exposed in URLs and APIs');
            $table->string('name');
            $table->char('city_code', 3)->nullable();
            $table->unsignedMediumInteger('state_id');
            $table->unsignedMediumInteger('country_id');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['country_id', 'state_id', 'name']);
            $table->index('state_id');
            $table->foreign('state_id')
                ->references('id')
                ->on('loc_states')
                ->restrictOnDelete();
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
        Schema::dropIfExists('loc_cities');
    }
};
