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
        Schema::create('ci_tashrihi', function (Blueprint $table) {
            $table->id(); // Modern Laravel way - uses bigIncrements internally

            $table->unsignedInteger('term')->default(1); // ترم
            $table->unsignedInteger('book_id')->nullable(); // کتاب مرتبط
            $table->unsignedTinyInteger('category')->default(1); // دسته‌بندی
            $table->text('question')->nullable(); // سؤال
            $table->text('answer')->nullable(); // پاسخ
            $table->unsignedInteger('page'); // شماره صفحه
            $table->decimal('barom', 5, 2)->default(0); // بارم یا نمره - using decimal for precision
            $table->string('testnumber', 255)->nullable(); // شماره تست (اختیاری)

            // Indexes - Laravel will auto-generate index names
            $table->index('book_id');
            $table->index('term');
            $table->index('category');
            
            // Composite index for common query patterns
            $table->index(['book_id', 'term']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_tashrihi');
    }
};
