<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ci_instruments', function (Blueprint $table) {
            $table->increments('id');       // int(11) + PRIMARY KEY
            $table->integer('submitted');    // int(11) NOT NULL
            $table->date('date');            // date NOT NULL
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ci_instruments');
    }
};
