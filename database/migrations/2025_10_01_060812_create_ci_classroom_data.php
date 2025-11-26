<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ci_classroom_data', function (Blueprint $table) {
            // Primary Key
            $table->bigIncrements('id'); // bigint(20) AUTO_INCREMENT + PRIMARY KEY

            // Columns
            $table->integer('cid');                   // int(11)
            $table->string('data_type', 255);         // varchar(255)
            $table->integer('data_id');               // int(11)
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ci_classroom_data');
    }
};
