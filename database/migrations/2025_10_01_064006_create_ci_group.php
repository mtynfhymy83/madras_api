<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ci_group', function (Blueprint $table) {
            // Primary Key
            $table->increments('id'); // int(11) AUTO_INCREMENT + PRIMARY KEY

            // Columns
            $table->integer('book_id');                 // int(11) NOT NULL
            $table->integer('parent');                  // int(11) NOT NULL
            $table->integer('position')->nullable();    // int(11) NULL
            $table->string('name', 255);                // varchar(255) NOT NULL
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ci_group');
    }
};
