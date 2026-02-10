<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('books', function (Blueprint $table) {
            $table->id();
            $table->string('title', 300);
            $table->text('excerpt')->nullable();


            // Foreign Keys
            $table->unsignedBigInteger('publisher_id')->nullable();
            $table->unsignedBigInteger('category')->nullable();
            $table->unsignedBigInteger('author')->nullable();

            // Media & Files

            $table->string('thumb')->nullable();
            $table->string('icon', 50)->nullable();

            // Book Properties
            $table->integer('pages')->unsigned()->nullable();
            $table->bigInteger('size')->unsigned()->nullable();
            $table->tinyInteger('has_sound')->nullable();
            $table->tinyInteger('has_describe')->nullable();
            $table->tinyInteger('has_test')->nullable();
            $table->tinyInteger('has_tashrihi')->nullable();
            // Features as JSONB


            // Cache fields



            // Pricing
            $table->decimal('price', 12, 2)->default(0);
            $table->decimal('discount_price', 12, 2)->nullable();
            $table->boolean('is_free')->default(false);

            // SEO
            $table->string('meta_keywords')->nullable();
            $table->string('meta_description')->nullable();
            $table->text('tags')->nullable();

            // Status
            $table->tinyInteger('accept_cm')->nullable();
            $table->tinyInteger('published')->nullable();
            $table->tinyInteger('draft')->nullable();

            $table->timestamps();


            // Indexes
            $table->index('title');
            $table->index('category');
            $table->index('price');


        });

        // Full-Text Search Indexes

    }

    public function down(): void
    {

        Schema::dropIfExists('books');
    }
};
