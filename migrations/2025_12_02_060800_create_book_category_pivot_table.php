<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('book_category', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->onDelete('cascade');
            $table->foreignId('category_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            // Unique constraint: یک کتاب نمی‌تواند در یک دسته‌بندی تکراری باشد
            $table->unique(['book_id', 'category_id']);
            
            // Indexes
            $table->index('book_id');
            $table->index('category_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('book_category');
    }
};

