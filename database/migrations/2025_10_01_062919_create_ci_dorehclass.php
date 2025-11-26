<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ci_dorehclass', function (Blueprint $table) {
            $table->id();

            $table->boolean('published')->default(false);
            $table->string('title', 255);
            $table->unsignedInteger('classid');
            $table->unsignedInteger('dorehid');
            $table->unsignedInteger('ostadid');
            $table->unsignedInteger('placeid');
            $table->unsignedInteger('jalasat')->default(0);
            $table->unsignedInteger('startdate');
            $table->string('starttime', 255);
            $table->unsignedInteger('price')->default(0);
            $table->unsignedInteger('user_id');
            $table->string('image', 255);
            $table->text('description');
            $table->unsignedBigInteger('createdate');
            $table->unsignedBigInteger('upddate');
            $table->unsignedTinyInteger('offer')->default(0);

            // Indexes
            $table->index('published');
            $table->index('dorehid');
            $table->index('classid');
            $table->index('ostadid');
            $table->index('user_id');
            $table->index('startdate');
            $table->index(['dorehid', 'published']);
            $table->index(['classid', 'startdate']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ci_dorehclass');
    }
};
