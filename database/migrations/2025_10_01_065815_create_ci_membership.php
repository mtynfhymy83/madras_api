<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ci_membership', function (Blueprint $table) {
            $table->integer('id'); // int(11) NOT NULL
            $table->string('title', 255); // varchar(255) NOT NULL
            $table->integer('price'); // int(11) NOT NULL
            $table->text('description'); // text NOT NULL
            $table->string('image', 255); // varchar(255) NOT NULL
            $table->timestamp('regdate')->useCurrent(); // timestamp NOT NULL DEFAULT current_timestamp()
            $table->integer('allowmonths'); // int(11) NOT NULL
            $table->integer('published'); // int(11) NOT NULL
            $table->integer('user_id'); // int(11) NOT NULL
            $table->integer('countmember'); // int(11) NOT NULL
            $table->string('pic', 255)->nullable(); // varchar(255) NULL
            $table->string('icon', 255)->nullable(); // varchar(255) NULL

            $table->primary('id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ci_membership');
    }
};
