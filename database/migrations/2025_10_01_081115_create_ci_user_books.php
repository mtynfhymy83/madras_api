<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ci_user_books', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id')->nullable();
            $table->unsignedInteger('book_id')->nullable();
            $table->unsignedInteger('factor_id')->nullable();
            $table->boolean('need_update')->default(false);
            $table->date('expiremembership')->nullable();

            // Indexes
            $table->index('user_id');
            $table->index('book_id');
            $table->index('factor_id');
            $table->index('expiremembership');
            $table->index(['user_id', 'book_id']);
            $table->index(['user_id', 'expiremembership']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ci_user_books');
    }
};
