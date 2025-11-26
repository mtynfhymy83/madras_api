<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ci_doreh', function (Blueprint $table) {
            $table->id();

            $table->boolean('published')->default(false);
            $table->unsignedInteger('classcount')->default(0);
            $table->unsignedInteger('tecatid');
            $table->unsignedInteger('supplierid');
            $table->unsignedInteger('placeid');
            $table->unsignedInteger('user_id');
            $table->string('image', 255);
            $table->text('description');
            $table->unsignedBigInteger('createdate');
            $table->unsignedBigInteger('upddate');
            $table->unsignedInteger('tahsili_year');
            $table->unsignedTinyInteger('offer')->default(0);

            // Indexes
            $table->index('published');
            $table->index('tecatid');
            $table->index('supplierid');
            $table->index('user_id');
            $table->index('createdate');
            $table->index(['published', 'tecatid']);
            $table->index(['user_id', 'createdate']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ci_doreh');
    }
};
