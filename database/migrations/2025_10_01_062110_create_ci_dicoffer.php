<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ci_dicoffer', function (Blueprint $table) {
            // Primary Key
            $table->bigIncrements('id'); // bigint(20) AUTO_INCREMENT + PRIMARY KEY

            // Columns
            $table->bigInteger('kid');                 // bigint(20)
            $table->text('translate');                 // text
            $table->integer('uid');                     // int(11)
            $table->timestamp('regdate')->useCurrent(); // timestamp DEFAULT current_timestamp()
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ci_dicoffer');
    }
};
