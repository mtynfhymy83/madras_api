<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ci_leitbox', function (Blueprint $table) {
            $table->id();                  // bigint(20) PRIMARY KEY
            $table->string('title', 255);  // varchar(255) NOT NULL
            $table->integer('remember');   // int(11) NOT NULL
            $table->integer('user_id');    // int(11) NOT NULL
            $table->timestamp('regdate')->useCurrent(); // timestamp NOT NULL DEFAULT current_timestamp()
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ci_leitbox');
    }
};
