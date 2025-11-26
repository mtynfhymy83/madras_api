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
        Schema::create('ci_posts', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('position')->nullable();
            $table->string('type', 50)->default('post');
            $table->string('title', 300)->nullable();
            $table->text('content')->nullable();
            $table->text('content_string')->nullable();
            $table->text('excerpt')->nullable();
            $table->string('category', 255)->nullable();
            $table->text('tags')->nullable();
            $table->string('meta_keywords', 255)->nullable();
            $table->string('meta_description', 255)->nullable();
            $table->string('thumb', 1000)->nullable();
            $table->string('icon', 30)->nullable();
            $table->unsignedInteger('author')->nullable();
            $table->boolean('accept_cm')->default(true);
            $table->boolean('published')->default(false);
            $table->boolean('draft')->default(false);
            $table->timestamp('date_modified')->nullable();
            $table->dateTime('date')->nullable();
            $table->unsignedInteger('special')->default(0);
            $table->unsignedInteger('size')->default(0)->comment('حجم کتاب');
            $table->unsignedInteger('price')->default(0)->comment('قیمت');
            $table->unsignedInteger('pages')->default(0)->comment('تعداد صفحه');
            $table->unsignedInteger('part_count')->default(0)->comment('تعداد پاراگراف');
            $table->boolean('has_description')->default(false)->comment('دارای شرح');
            $table->boolean('has_sound')->default(false)->comment('دارای صوت');
            $table->boolean('has_video')->default(false)->comment('دارای ویدئو');
            $table->boolean('has_image')->default(false)->comment('دارای تصویر');
            $table->boolean('has_test')->default(false)->comment('دارای آزمون تستی');
            $table->boolean('has_tashrihi')->default(false)->comment('دارای آزمون تشریحی');
            $table->boolean('has_download')->default(false)->comment('دانلود');
            $table->boolean('has_bought')->default(false)->comment('خریداری شده');
            $table->boolean('has_membership')->default(false)->comment('عضویت');

            // Indexes for commonly queried fields
            $table->index('author');
            $table->index('type');
            $table->index('published');
            $table->index('category');
            $table->index('date');
            $table->index(['type', 'published']); // Composite index for common queries
            $table->index(['author', 'published']); // Composite index for author posts
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_posts');
    }
};
