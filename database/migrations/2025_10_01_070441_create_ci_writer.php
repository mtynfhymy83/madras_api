<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ci_writer', function (Blueprint $table) {
            $table->id(); // bigint(20) PRIMARY AUTO_INCREMENT
            $table->string('title', 255); // varchar(255) NOT NULL
            $table->integer('uid'); // int(11) NOT NULL
            $table->timestamp('regdate')->useCurrent(); // timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ci_writer');
    }
};
