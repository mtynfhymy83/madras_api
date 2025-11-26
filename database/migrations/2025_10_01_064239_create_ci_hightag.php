<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ci_hightag', function (Blueprint $table) {
            $table->bigIncrements('id');      // bigint(20) + PRIMARY KEY
            $table->integer('user_id');       // int(11) NOT NULL
            $table->integer('hid');           // int(11) NOT NULL
            $table->tinyInteger('public');    // tinyint(1) NOT NULL
            $table->string('title', 255);     // varchar(255) NOT NULL
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ci_hightag');
    }
};
