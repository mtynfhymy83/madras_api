<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ci_dictionary', function (Blueprint $table) {
            // Primary Key
            $table->bigIncrements('id'); // bigint(20) AUTO_INCREMENT + PRIMARY KEY

            // Columns
            $table->integer('fromlang');               // int(11)
            $table->integer('tolang');                 // int(11)
            $table->string('kalameh', 255);           // varchar(255)
            $table->text('translate');                 // text
            $table->integer('uid');                     // int(11)
            $table->timestamp('regdate')->useCurrent(); // timestamp DEFAULT current_timestamp()
            $table->integer('offer');                  // int(11)
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ci_dictionary');
    }
};
