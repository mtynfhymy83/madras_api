<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ci_notes', function (Blueprint $table) {
            $table->increments('id'); // int(11) PRIMARY AUTO_INCREMENT
            $table->integer('part_id')->nullable(); // int(11) NULL
            $table->integer('user_id')->nullable(); // int(11) NULL
            $table->string('title', 255)->nullable(); // varchar(255) NULL
            $table->text('text')->nullable(); // text NULL
            $table->text('user_text')->nullable(); // text NULL
            $table->mediumInteger('start')->nullable(); // mediumint(9) NULL
            $table->mediumInteger('end')->nullable(); // mediumint(9) NULL
            $table->tinyInteger('sharh')->nullable(); // tinyint(1) NULL
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ci_notes');
    }
};
