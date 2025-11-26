<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ci_classroom', function (Blueprint $table) {
            // Primary Key
            $table->bigIncrements('id'); // int(11) AUTO_INCREMENT + PRIMARY KEY

            // Columns
            $table->boolean('published');            // tinyint(1)
            $table->integer('mecatid');              // int(11)
            $table->string('title', 255);            // varchar(255)
            $table->integer('placeid');              // int(11)
            $table->integer('user_id');              // int(11)
            $table->string('image', 255);            // varchar(255)
            $table->text('description');             // text
            $table->bigInteger('createdate');        // bigint(20)
            $table->bigInteger('upddate');           // bigint(20)
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ci_classroom');
    }
};
