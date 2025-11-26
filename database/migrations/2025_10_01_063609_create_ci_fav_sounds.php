<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ci_fav_sounds', function (Blueprint $table) {
            // Primary Key
            $table->bigIncrements('id'); // int(11) AUTO_INCREMENT + PRIMARY KEY

            // Columns
            $table->integer('user_id')->nullable(); // int(11) YES NULL
            $table->integer('part_id')->nullable(); // int(11) YES NULL
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ci_fav_sounds');
    }
};
