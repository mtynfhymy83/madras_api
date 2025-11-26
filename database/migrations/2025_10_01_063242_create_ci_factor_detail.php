<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ci_factor_detail', function (Blueprint $table) {
            $table->id();

            $table->unsignedInteger('factor_id');
            $table->unsignedInteger('book_id');
            $table->unsignedInteger('price')->default(0);
            $table->unsignedInteger('discount')->default(0);

            // Indexes
            $table->index('factor_id');
            $table->index('book_id');
            $table->index(['factor_id', 'book_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ci_factor_detail');
    }
};
