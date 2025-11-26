<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ci_jalasat', function (Blueprint $table) {
            $table->id();                     // bigint(20) PRIMARY KEY
            $table->integer('dorehclassid');  // int(11) NOT NULL
            $table->string('title', 255);     // varchar(255) NOT NULL
            $table->integer('startdate');     // int(11) NOT NULL
            $table->string('starttime', 255); // varchar(255) NOT NULL
            $table->text('description');      // text NOT NULL
            $table->integer('subjalase');     // int(11) NOT NULL
            $table->integer('user_id');       // int(11) NOT NULL
            $table->boolean('published');     // tinyint(1) NOT NULL
            $table->integer('createdate');    // int(11) NOT NULL
            $table->integer('upddate');       // int(11) NOT NULL
            $table->integer('image');         // int(11) NOT NULL
            $table->integer('pdf');           // int(11) NOT NULL
            $table->integer('audio');         // int(11) NOT NULL
            $table->integer('video');         // int(11) NOT NULL
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ci_jalasat');
    }
};
