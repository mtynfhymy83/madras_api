<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ci_user_level', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('level_id');
            $table->string('level_key', 100);
            $table->string('level_value', 100);

            // Indexes
            $table->index('level_id');
            $table->index('level_key');
            $table->unique(['level_key', 'level_value']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ci_user_level');
    }
};
