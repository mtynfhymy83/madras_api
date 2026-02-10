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
        Schema::create('book_details', function (Blueprint $table) {

            $table->foreignId('book_id')->unique()->constrained('books')->onDelete('cascade');;
            $table->unsignedBigInteger('publisher_id')->nullable();
            $table->unsignedBigInteger('category')->nullable();
            $table->unsignedBigInteger('author')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('book_details');
    }
};
