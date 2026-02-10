<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_library', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('book_id')->constrained()->onDelete('cascade');

            // Reading Progress (lightweight - detailed analytics in reading_sessions)
            $table->decimal('progress_percent', 5, 2)->default(0);
            $table->integer('current_page')->default(0);
            $table->string('status', 30)->default('not_started'); // not_started, reading, completed

            // Timestamps
            $table->timestamp('last_read_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            // Indexes
            $table->index(['user_id', 'last_read_at']);
            $table->index(['user_id', 'status']);
            
            // Unique: یک کتاب یکبار در کتابخانه
            $table->unique(['user_id', 'book_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_library');
    }
};
