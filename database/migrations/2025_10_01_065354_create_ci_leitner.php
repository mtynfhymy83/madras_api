<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ci_leitner', function (Blueprint $table) {
            $table->id(); // bigint(20) PRIMARY KEY
            $table->integer('user_id'); // int(11) NOT NULL
            $table->bigInteger('lid'); // bigint(20) NOT NULL
            $table->integer('catid'); // int(11) NOT NULL
            $table->string('title', 255); // varchar(255) NOT NULL
            $table->text('description'); // text NOT NULL
            $table->tinyInteger('level'); // tinyint(1) NOT NULL
            $table->timestamp('regdate')->useCurrent(); // timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
            $table->dateTime('readdate')->nullable(); // datetime NULL
            $table->integer('readcount'); // int(11) NOT NULL
            $table->integer('trueanswer'); // int(11) NOT NULL
            $table->integer('falseanswer'); // int(11) NOT NULL
            $table->integer('bookid'); // int(11) NOT NULL
            $table->text('tag'); // text NOT NULL
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ci_leitner');
    }
};
