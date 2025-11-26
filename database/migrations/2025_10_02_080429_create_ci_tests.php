<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ci_tests', function (Blueprint $table) {
            $table->id();

            $table->unsignedInteger('term')->default(1);
            $table->unsignedInteger('book_id')->nullable();
            $table->unsignedSmallInteger('category')->default(1);
            $table->text('question')->nullable();
            $table->unsignedSmallInteger('true_answer')->nullable();
            $table->text('answer_1')->nullable();
            $table->text('answer_2')->nullable();
            $table->text('answer_3')->nullable();
            $table->text('answer_4');
            $table->unsignedInteger('page');
            $table->string('testnumber', 255)->nullable();

            // Indexes
            $table->index('book_id');
            $table->index('term');
            $table->index('category');
            $table->index('page');
            $table->index(['book_id', 'term']);
            $table->index(['book_id', 'page']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ci_tests');
    }
};
