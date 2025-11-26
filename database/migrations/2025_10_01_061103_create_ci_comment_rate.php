<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ci_comment_rate', function (Blueprint $table) {
            $table->unsignedInteger('comment_id');
            $table->unsignedInteger('rate_id');

            $table->primary(['comment_id', 'rate_id']);
            $table->index('comment_id');
            $table->index('rate_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ci_comment_rate');
    }
};
