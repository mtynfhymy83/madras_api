<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ci_logged_in', function (Blueprint $table) {
            $table->integer('user_id')->nullable(); // int(11) NULL
            $table->string('mac', 17);              // varchar(17) NOT NULL
            $table->string('token', 32)->nullable(); // varchar(32) NULL
            $table->integer('date')->nullable();     // int(11) NULL
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ci_logged_in');
    }
};
