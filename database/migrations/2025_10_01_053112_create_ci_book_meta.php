<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ci_paragraphs', function (Blueprint $table) {
            // Primary Key - id() automatically creates primary key
            $table->id();

            // Columns
            $table->unsignedInteger('book_id')->nullable();
            $table->unsignedInteger('order')->nullable();
            $table->text('text')->nullable();
            $table->text('description')->nullable();
            $table->string('sound', 255)->nullable();
            $table->text('image')->nullable();
            $table->unsignedInteger('index')->nullable();
            $table->text('video')->nullable();
            $table->unsignedInteger('page');
            $table->unsignedInteger('paragraph');
            $table->unsignedInteger('fehrest');

            // Single column indexes for common filters
            $table->index('book_id');
            $table->index('page');
            $table->index('paragraph');
            $table->index('order');
            $table->index('fehrest');
            
            // Composite indexes for common query patterns
            $table->index(['book_id', 'page']); // Paragraphs by book and page
            $table->index(['book_id', 'paragraph']); // Paragraphs by book and paragraph number
            $table->index(['book_id', 'order']); // Paragraphs by book and order
            $table->index(['book_id', 'page', 'paragraph']); // Specific paragraph lookup
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ci_paragraphs');
    }
};
