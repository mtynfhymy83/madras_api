<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ci_user_doreh', function (Blueprint $table) {
            $table->id(); // id int(11) NOT NULL AUTO_INCREMENT
            $table->integer('user_id')->nullable(); // user_id int(11) NULL
            $table->integer('dorehclassid')->nullable(); // dorehclassid int(11) NULL
            $table->integer('factor_id')->nullable(); // factor_id int(11) NULL
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ci_user_doreh');
    }
};
