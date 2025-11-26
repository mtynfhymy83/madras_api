<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ci_classfactor_detail', function (Blueprint $table) {
            // Primary Key
            $table->bigIncrements('id'); // int(11) AUTO_INCREMENT + PRIMARY KEY

            // Columns
            $table->integer('factor_id');       // int(11) NOT NULL
            $table->integer('dorehid');         // int(11) NOT NULL
            $table->integer('class_id');        // int(11) NOT NULL
            $table->integer('dorehclassid');   // int(11) NOT NULL
            $table->integer('price');           // int(11) NOT NULL
            $table->integer('discount');        // int(11) NOT NULL
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ci_classfactor_detail');
    }
};
