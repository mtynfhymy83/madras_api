<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ci_catquest', function (Blueprint $table) {
            $table->id();

            $table->string('title', 255);
            $table->unsignedInteger('uid');
            $table->timestamp('regdate')->useCurrent();

            // Indexes
            $table->index('uid');
            $table->index('regdate');
            $table->index(['uid', 'regdate']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ci_catquest');
    }
};
