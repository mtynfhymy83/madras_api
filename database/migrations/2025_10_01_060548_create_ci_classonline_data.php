<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ci_classonline_data', function (Blueprint $table) {
            // Primary Key
            $table->bigIncrements('id'); // bigint(20) AUTO_INCREMENT + PRIMARY KEY

            // Columns
            $table->integer('cid');                   // int(11)
            $table->string('data_type', 255);         // varchar(255)
            $table->integer('data_id');               // int(11)
            $table->integer('startpage');             // int(11)
            $table->integer('endpage');               // int(11)
            $table->integer('dayofweek')->default(0); // int(11) DEFAULT 0
            $table->string('starttime', 5)->nullable(); // varchar(5) NULLABLE
            $table->string('endtime', 5)->nullable();   // varchar(5) NULLABLE
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ci_classonline_data');
    }
};
