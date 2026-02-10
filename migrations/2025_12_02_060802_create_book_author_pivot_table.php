<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('book_author', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->onDelete('cascade');
            $table->foreignId('author_id')->constrained()->onDelete('cascade');
            $table->integer('order')->default(0); // ترتیب نویسندگان
            $table->timestamps();

            // Unique constraint: یک نویسنده نمی‌تواند دو بار برای یک کتاب باشد
            $table->unique(['book_id', 'author_id']);
            
            // Indexes
            $table->index('book_id');
            $table->index('author_id');
            $table->index(['book_id', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('book_author');
    }
};

