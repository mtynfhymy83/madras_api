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
        Schema::create('ci_short_links', function (Blueprint $table) {
            $table->increments('id'); // int(10) UNSIGNED primary auto-increment
            $table->string('short_code')->index(); // varchar(255) indexed
            $table->text('original_url'); // متن لینک اصلی
            $table->integer('click_count')->default(0); // تعداد کلیک‌ها
            $table->timestamp('created_at')->useCurrent()->useCurrentOnUpdate(); // timestamp با مقدار پیش‌فرض و بروزرسانی خودکار
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_short_links');
    }
};
