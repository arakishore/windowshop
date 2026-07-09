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
        Schema::create('loc_regions', function (Blueprint $table) {
            $table->mediumIncrements('id');
            $table->uuid('uuid')
                ->nullable()
                ->unique()
                ->comment('Public identifier exposed in URLs and APIs');
            $table->string('name', 100)->unique();
            $table->text('translations')
                ->nullable()
                ->comment('Localized region names');
            $table->boolean('flag')
                ->default(true)
                ->comment('1=active,0=inactive')
                ->index();
            $table->string('wiki_data_id')
                ->nullable()
                ->comment('Rapid API GeoDB Cities WikiData identifier');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loc_regions');
    }
};
