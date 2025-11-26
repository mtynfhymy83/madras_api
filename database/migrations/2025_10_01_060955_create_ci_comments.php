<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ci_comments', function (Blueprint $table) {
            $table->id();

            $table->unsignedInteger('parent')->default(0);
            $table->string('table', 20);
            $table->unsignedInteger('row_id');
            $table->boolean('submitted')->default(false);
            $table->unsignedInteger('user_id');
            $table->string('name', 255);
            $table->string('email', 255)->nullable();
            $table->text('text');
            $table->string('ip', 50);
            $table->timestamp('date')->useCurrent();

            // Indexes
            $table->index('parent');
            $table->index('user_id');
            $table->index('row_id');
            $table->index('submitted');
            $table->index('date');
            $table->index(['table', 'row_id']);
            $table->index(['table', 'row_id', 'submitted']);
            $table->index(['user_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ci_comments');
    }
};
