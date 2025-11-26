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
        Schema::create('ci_questions', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('catid');
            $table->unsignedInteger('qid');
            $table->text('content');
            $table->string('image', 255)->nullable();
            $table->string('sound', 255)->nullable();
            $table->unsignedInteger('user_id');
            $table->timestamp('regdate')->useCurrent();
            $table->boolean('published')->default(false);

            // Indexes
            $table->index('catid');
            $table->index('qid');
            $table->index('user_id');
            $table->index('published');
            $table->index('regdate');
            $table->index(['catid', 'published']);
            $table->index(['user_id', 'regdate']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_questions');
    }
};
