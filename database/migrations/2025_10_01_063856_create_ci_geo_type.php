<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ci_geotype', function (Blueprint $table) {
            // Primary Key
            $table->bigIncrements('id'); // bigint(20) AUTO_INCREMENT + PRIMARY KEY

            // Columns
            $table->string('title', 255);          // varchar(255) NOT NULL
            $table->tinyInteger('published');      // tinyint(1) NOT NULL
            $table->integer('uid');                // int(11) NOT NULL
            $table->timestamp('regdate')->useCurrent(); // timestamp NOT NULL DEFAULT current_timestamp()
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ci_geotype');
    }
};
