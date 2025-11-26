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
        Schema::create('ci_suppliertype', function (Blueprint $table) {
            $table->id(); // bigint(20) primary auto_increment
            $table->string('title', 255); // عنوان
            $table->tinyInteger('isplace'); // آیا مربوط به مکان است
            $table->string('datatype', 255)->nullable(); // نوع داده
            $table->integer('uid'); // شناسه کاربر
            $table->timestamp('regdate')->useCurrent(); // تاریخ ثبت
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_suppliertype');
    }
};
