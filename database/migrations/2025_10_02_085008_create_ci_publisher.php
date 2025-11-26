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
        Schema::create('ci_publisher', function (Blueprint $table) {
            $table->id(); // bigint(20) primary key auto-increment
            $table->string('title', 255);
            $table->integer('uid');
            $table->timestamp('regdate')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_publisher');
    }
};
