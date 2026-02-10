<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('book_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('book_id')->constrained('products')->onDelete('cascade');
            
            // Review content
            $table->tinyInteger('rating')->unsigned(); // 1-5 stars
            $table->text('text')->nullable(); // Review text (optional)
            
            // Engagement
            $table->integer('likes_count')->unsigned()->default(0);
            
            // Moderation
            $table->boolean('is_approved')->default(true); // Auto-approve or manual
            
            $table->timestamps();
            $table->softDeletes();

            // Indexes for fast queries
            $table->index('book_id');
            $table->index(['book_id', 'created_at']);
            $table->index(['book_id', 'rating']);
            $table->index(['book_id', 'likes_count']);
            
            // One review per user per book
            $table->unique(['user_id', 'book_id'], 'user_book_review_unique');
        });

        // Table for review likes (who liked which review)
        Schema::create('book_review_likes', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('review_id')->constrained('book_reviews')->onDelete('cascade');
            $table->timestamp('created_at')->useCurrent();
            
            $table->primary(['user_id', 'review_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('book_review_likes');
        Schema::dropIfExists('book_reviews');
    }
};
