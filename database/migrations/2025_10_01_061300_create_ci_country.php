<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ci_country', function (Blueprint $table) {
            // Primary Key
            $table->bigIncrements('id'); // int(11) AUTO_INCREMENT + PRIMARY KEY

            // Columns
            $table->string('summary', 4);  // varchar(4)
            $table->string('title', 20);   // varchar(20)
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ci_country');
    }
};
