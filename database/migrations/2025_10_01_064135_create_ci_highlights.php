<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ci_highlights', function (Blueprint $table) {
            // Primary Key
            $table->increments('id'); // int(11) + PRIMARY KEY

            // Columns
            $table->integer('user_id')->nullable();       // int(11) NULL
            $table->integer('part_id')->nullable();       // int(11) NULL
            $table->integer('start')->nullable();         // int(11) NULL
            $table->integer('end')->nullable();           // int(11) NULL
            $table->string('title', 255);                 // varchar(255) NOT NULL
            $table->text('text')->nullable();             // text NULL
            $table->tinyInteger('color')->nullable();    // tinyint(4) NULL
            $table->tinyInteger('sharh')->nullable();    // tinyint(1) NULL
            $table->text('description');                  // text NOT NULL
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ci_highlights');
    }
};
