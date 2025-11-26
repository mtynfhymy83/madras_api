<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ci_user_classonline', function (Blueprint $table) {
            $table->id(); // id int(11) NOT NULL AUTO_INCREMENT
            $table->integer('user_id'); // user_id int(11) NOT NULL
            $table->integer('factor_id'); // factor_id int(11) NOT NULL
            $table->integer('classonline_id'); // classonline_id int(11) NOT NULL
            $table->date('startdate')->nullable(); // startdate date NULL
            $table->date('enddate')->nullable();   // enddate date NULL
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ci_user_classonline');
    }
};
