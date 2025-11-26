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
        Schema::create('ci_settings', function (Blueprint $table) {
            $table->increments('id'); // int(11) primary key auto-increment
            $table->string('name', 255); // نام تنظیمات
            $table->text('value'); // مقدار تنظیمات
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_settings');
    }
};
