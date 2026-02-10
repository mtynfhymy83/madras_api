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
        // Optimize books table with better trigram index
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        DB::statement('
            CREATE INDEX IF NOT EXISTS books_title_trgm_gin_idx 
            ON books USING gin(title gin_trgm_ops)
        ');

        // Optimize user_library with composite index for common queries
        Schema::table('user_library', function (Blueprint $table) {
            $table->index(['user_id', 'status', 'last_read_at'], 'user_library_status_read_idx');
        });

        // Optimize purchases for reporting queries
        Schema::table('purchases', function (Blueprint $table) {
            $table->index(['user_id', 'status', 'created_at'], 'purchases_user_status_date_idx');
        });

        // Add index on book_stats for leaderboard/top queries
        Schema::table('book_stats', function (Blueprint $table) {
            $table->index(['view_count', 'rating'], 'book_stats_popular_idx');
        });

        // Add composite index for book_contents navigation
        Schema::table('book_contents', function (Blueprint $table) {
            $table->index(['book_id', 'page_number', 'order'], 'book_contents_navigation_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS books_title_trgm_gin_idx');
        
        Schema::table('user_library', function (Blueprint $table) {
            $table->dropIndex('user_library_status_read_idx');
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->dropIndex('purchases_user_status_date_idx');
        });

        Schema::table('book_stats', function (Blueprint $table) {
            $table->dropIndex('book_stats_popular_idx');
        });

        Schema::table('book_contents', function (Blueprint $table) {
            $table->dropIndex('book_contents_navigation_idx');
        });
    }
};

