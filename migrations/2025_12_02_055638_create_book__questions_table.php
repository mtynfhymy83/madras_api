<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // سوالات تستی
        Schema::create('book_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->onDelete('cascade');
            $table->foreignId('content_id')->nullable()->constrained('book_contents')->onDelete('set null'); // مرتبط با کدوم پاراگراف

            $table->enum('type', ['multiple_choice', 'true_false', 'essay', 'fill_blank']); // نوع سوال
            $table->text('question_text'); // متن سوال
            $table->string('question_image')->nullable(); // تصویر سوال
            $table->integer('difficulty_level')->default(1); // سطح سختی 1-5
            $table->integer('order')->default(0);

            // برای تست چند گزینه‌ای
            $table->json('options')->nullable(); // گزینه‌ها ["گزینه 1", "گزینه 2", ...]
            $table->string('correct_answer')->nullable(); // پاسخ صحیح

            // توضیحات
            $table->text('explanation')->nullable(); // توضیح پاسخ
            $table->string('explanation_image')->nullable();
            $table->string('explanation_video')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Indexes
            $table->index('book_id');
            $table->index('content_id');
            $table->index(['book_id', 'type']);
            $table->index(['book_id', 'is_active']);
            
            // Note: paragraph_id changed to content_id
        });

        // پاسخ‌های کاربران به سوالات
        Schema::create('user_question_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('question_id')->constrained('book_questions')->onDelete('cascade');

            $table->text('user_answer'); // پاسخ کاربر
            $table->boolean('is_correct')->nullable(); // صحیح/غلط (برای تست)
            $table->integer('score')->nullable(); // نمره (برای تشریحی)

            $table->timestamp('answered_at');
            $table->timestamps();

            // Indexes
            $table->index('user_id');
            $table->index('question_id');
            $table->index(['user_id', 'question_id']);
            $table->index('answered_at');
        });

        // آزمون‌ها (مجموعه سوالات)
        Schema::create('book_exams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->onDelete('cascade');

            $table->string('title');
            $table->text('description')->nullable();
            $table->integer('duration_minutes')->nullable(); // مدت زمان (دقیقه)
            $table->integer('passing_score')->default(60); // نمره قبولی
            $table->integer('total_score')->default(100);
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            // Indexes
            $table->index('book_id');
            $table->index('is_active');
        });

        // سوالات هر آزمون
        Schema::create('exam_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained('book_exams')->onDelete('cascade');
            $table->foreignId('question_id')->constrained('book_questions')->onDelete('cascade');
            $table->integer('order')->default(0);
            $table->integer('score')->default(1); // امتیاز این سوال در آزمون

            $table->timestamps();

            // Indexes
            $table->index('exam_id');
            $table->index('question_id');
            $table->unique(['exam_id', 'question_id']);
        });
    }
};
