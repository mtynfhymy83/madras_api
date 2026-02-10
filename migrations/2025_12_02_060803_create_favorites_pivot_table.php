<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('book_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            // Unique constraint: یک کاربر نمی‌تواند یک کتاب را دو بار به علاقه‌مندی‌ها اضافه کند
            $table->unique(['user_id', 'book_id']);
            
            // Indexes
            $table->index('user_id');
            $table->index('book_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('favorites');
    }
};

