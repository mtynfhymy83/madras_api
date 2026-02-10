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
        Schema::create('book_stats', function (Blueprint $table) {
            $table->foreignId('book_id')->primary()->constrained('books')->onDelete('cascade');
            $table->bigInteger('view_count')->unsigned()->default(0);
            $table->bigInteger('purchase_count')->unsigned()->default(0);
            $table->bigInteger('download_count')->unsigned()->default(0);
            $table->decimal('rating', 3, 2)->default(0);
            $table->integer('rating_count')->unsigned()->default(0);
            $table->integer('favorite_count')->unsigned()->default(0);
            $table->integer('comment_count')->unsigned()->default(0);
            $table->timestamp('updated_at')->nullable();

            // Indexes for sorting and filtering
            $table->index('view_count');
            $table->index('purchase_count');
            $table->index('rating');
            $table->index(['rating', 'rating_count']);
        });

        // Trigger to auto-create book_stats when a book is created
        DB::statement("
            CREATE OR REPLACE FUNCTION create_book_stats()
            RETURNS TRIGGER AS $$
            BEGIN
                INSERT INTO book_stats (book_id, updated_at)
                VALUES (NEW.id, NOW())
                ON CONFLICT (book_id) DO NOTHING;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql
        ");

        DB::statement("
            CREATE TRIGGER book_stats_auto_create
            AFTER INSERT ON books
            FOR EACH ROW
            EXECUTE FUNCTION create_book_stats()
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS book_stats_auto_create ON books');
        DB::statement('DROP FUNCTION IF EXISTS create_book_stats');
        Schema::dropIfExists('book_stats');
    }
};


