<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ci_favorites', function (Blueprint $table) {
            $table->id();

            $table->unsignedInteger('user_id');
            $table->string('section', 255);
            $table->unsignedInteger('section_id');
            $table->timestamp('created_at')->useCurrent();

            // Indexes
            $table->index('user_id');
            $table->index('section');
            $table->index('section_id');
            $table->index(['user_id', 'section']);
            $table->index(['section', 'section_id']);
            $table->unique(['user_id', 'section', 'section_id']); // Prevent duplicates
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ci_favorites');
    }
};
