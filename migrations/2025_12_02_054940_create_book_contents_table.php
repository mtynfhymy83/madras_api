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
        Schema::create('book_contents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->onDelete('cascade');
            
            // Structure
            $table->integer('page_number')->unsigned(); // شماره صفحه
            $table->integer('paragraph_number')->unsigned(); // شماره پاراگراف در صفحه
            $table->integer('order')->unsigned()->default(0); // ترتیب نمایش
            
            // Content
            $table->text('text')->nullable(); // متن پاراگراف
            $table->text('description')->nullable(); // شرح/توضیحات
            
            // Media (Relative paths to S3)
            $table->string('sound_path')->nullable(); // آدرس فایل صوتی
            $table->text('image_paths')->nullable(); // JSON array of image paths
            $table->string('video_path')->nullable(); // آدرس ویدیو
            
            // Index & Navigation
            $table->boolean('is_index')->default(false); // آیا جزو فهرست است؟
            $table->string('index_title')->nullable(); // عنوان در فهرست
            $table->integer('index_level')->unsigned()->default(0); // سطح فهرست (1,2,3,...)
            
            $table->timestamps();

            // Composite Indexes (Critical for Performance)
            $table->index('book_id');
            $table->index(['book_id', 'page_number']);
            $table->index(['book_id', 'page_number', 'paragraph_number']);
            $table->index(['book_id', 'order']);
            $table->index(['book_id', 'is_index']);
            
            // Unique constraint
            $table->unique(['book_id', 'page_number', 'paragraph_number'], 'book_page_paragraph_unique');
        });

        // Add tsvector column for full-text search
        DB::statement('ALTER TABLE book_contents ADD COLUMN tsv tsvector');
        
        // Create trigger to automatically update tsv column
        DB::statement("
            CREATE OR REPLACE FUNCTION book_contents_tsv_trigger() RETURNS trigger AS $$
            BEGIN
                NEW.tsv := to_tsvector('simple', COALESCE(NEW.text, ''));
                RETURN NEW;
            END
            $$ LANGUAGE plpgsql
        ");
        
        DB::statement("
            CREATE TRIGGER book_contents_tsv_update 
            BEFORE INSERT OR UPDATE ON book_contents
            FOR EACH ROW EXECUTE FUNCTION book_contents_tsv_trigger()
        ");

        // Full-Text Search Index
        DB::statement('CREATE INDEX book_contents_tsv_idx ON book_contents USING gin(tsv)');
        
        // Trigram Index for fuzzy search
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        DB::statement('CREATE INDEX book_contents_text_trgm_idx ON book_contents USING gin(text gin_trgm_ops)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS book_contents_tsv_update ON book_contents');
        DB::statement('DROP FUNCTION IF EXISTS book_contents_tsv_trigger');
        DB::statement('DROP INDEX IF EXISTS book_contents_tsv_idx');
        DB::statement('DROP INDEX IF EXISTS book_contents_text_trgm_idx');
        Schema::dropIfExists('book_contents');
    }
};



