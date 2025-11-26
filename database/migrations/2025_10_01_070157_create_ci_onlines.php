<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ci_onlines', function (Blueprint $table) {
            $table->increments('id'); // int(11) PRIMARY AUTO_INCREMENT
            $table->string('ip', 50); // varchar(50) NOT NULL
            $table->string('agent', 255); // varchar(255) NOT NULL
            $table->integer('user_id'); // int(11) NOT NULL
            $table->string('username', 50)->nullable(); // varchar(50) NULL
            $table->string('displayname', 50)->nullable(); // varchar(50) NULL
            $table->timestamp('date')->useCurrent()->useCurrentOnUpdate(); // timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ci_onlines');
    }
};
