<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ci_jalasat_data', function (Blueprint $table) {
            $table->id();                   // bigint(20) PRIMARY KEY
            $table->integer('jid');         // int(11) NOT NULL
            $table->integer('bookid');      // int(11) NOT NULL
            $table->string('pages', 255);   // varchar(255) NOT NULL
            $table->string('image', 999);   // varchar(999) NOT NULL
            $table->string('pdf', 999);     // varchar(999) NOT NULL
            $table->string('audio', 999);   // varchar(999) NOT NULL
            $table->float('audio_duration'); // float NOT NULL
            $table->string('video', 999);   // varchar(999) NOT NULL
            $table->float('video_duration'); // float NOT NULL
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ci_jalasat_data');
    }
};
