<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ci_rates', function (Blueprint $table) {
            $table->increments('id'); // int(11) primary key auto-increment
            $table->string('user_id', 50);
            $table->string('ip', 50);
            $table->string('table', 20);
            $table->integer('row_id');
            $table->integer('rating')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_rates');
    }
};
