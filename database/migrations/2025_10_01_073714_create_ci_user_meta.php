<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ci_user_meta', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id');
            $table->string('meta_name', 1000);
            $table->text('meta_value')->nullable();

            // Indexes
            $table->index('user_id');
            $table->index('meta_name');
            $table->index(['user_id', 'meta_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ci_user_meta');
    }
};
