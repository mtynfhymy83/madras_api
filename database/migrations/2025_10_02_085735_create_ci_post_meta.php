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
        Schema::create('ci_post_meta', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('post_id');
            $table->string('meta_key', 200);
            $table->text('meta_value')->nullable();

            // Single column indexes for common filters
            $table->index('post_id');
            $table->index('meta_key');
            
            // Composite index for most common query pattern (get specific meta for a post)
            $table->index(['post_id', 'meta_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_post_meta');
    }
};
