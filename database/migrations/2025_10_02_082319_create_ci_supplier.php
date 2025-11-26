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
        Schema::create('ci_supplier', function (Blueprint $table) {
            $table->id(); // bigint(20) primary auto-increment
            $table->tinyInteger('optype');       // نوع عملیات
            $table->tinyInteger('stype');        // نوع تامین‌کننده
            $table->integer('smtype');           // زیرنوع
            $table->string('title', 255);        // عنوان
            $table->string('image', 255)->nullable(); // تصویر
            $table->string('phone', 255);        // شماره تلفن
            $table->string('mobile', 255);       // موبایل
            $table->text('address');             // آدرس
            $table->text('description');         // توضیحات
            $table->integer('ownerpercent');     // درصد مالک
            $table->integer('uid');              // شناسه کاربر/ثبت کننده
            $table->timestamp('regdate')->useCurrent(); // تاریخ ثبت
            $table->tinyInteger('offer');        // وضعیت پیشنهاد
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_supplier');
    }
};
