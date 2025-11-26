<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ci_city', function (Blueprint $table) {
            $table->id();

            $table->string('title', 255);
            $table->unsignedBigInteger('parent')->default(0);
            $table->unsignedInteger('ordering')->default(0);
            $table->boolean('published')->default(true);

            // Indexes
            $table->index('parent');
            $table->index('published');
            $table->index('ordering');
            $table->index(['parent', 'published']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ci_city');
    }
};
