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
        Schema::create('loc_subregions', function (Blueprint $table) {
            $table->mediumIncrements('id');
            $table->uuid('uuid')
                ->nullable()
                ->unique()
                ->comment('Public identifier exposed in URLs and APIs');
            $table->string('name', 100);
            $table->text('translations')
                ->nullable()
                ->comment('Localized subregion names');
            $table->unsignedMediumInteger('region_id');
            $table->boolean('flag')
                ->default(true)
                ->comment('1=active,0=inactive')
                ->index();
            $table->string('wiki_data_id')
                ->nullable()
                ->comment('Rapid API GeoDB Cities WikiData identifier');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['region_id', 'name']);
            $table->foreign('region_id')
                ->references('id')
                ->on('loc_regions')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loc_subregions');
    }
};
