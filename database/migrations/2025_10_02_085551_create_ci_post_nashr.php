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
        Schema::create('ci_post_nashr', function (Blueprint $table) {
            $table->id(); // int(11) primary key auto-increment
            $table->integer('post_id');
            $table->string('nashr_key', 200);
            $table->text('nashr_value')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_post_nashr');
    }
};
