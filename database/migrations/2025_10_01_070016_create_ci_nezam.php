<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ci_nezam', function (Blueprint $table) {
            $table->increments('id'); // int(11) PRIMARY AUTO_INCREMENT
            $table->integer('position'); // int(11) NOT NULL
            $table->string('name', 255); // varchar(255) NOT NULL
            $table->text('description')->nullable(); // text NULL
            $table->string('pic', 255)->nullable(); // varchar(255) NULL
            $table->string('icon', 30); // varchar(30) NOT NULL
            $table->integer('parent'); // int(11) NOT NULL
            $table->integer('user_id'); // int(11) NOT NULL
            $table->tinyInteger('special'); // tinyint(1) NOT NULL
            $table->tinyInteger('published'); // tinyint(1) NOT NULL
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ci_nezam');
    }
};
