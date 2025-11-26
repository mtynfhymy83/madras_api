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
        Schema::create('ci_tecat', function (Blueprint $table) {
            $table->increments('id'); // int(11) primary auto_increment

            $table->integer('position'); // ترتیب نمایش
            $table->string('name', 255); // نام دسته
            $table->text('description')->nullable(); // توضیحات
            $table->string('pic', 255)->nullable(); // تصویر
            $table->string('icon', 30); // آیکن
            $table->integer('parent'); // دسته والد (برای دسته‌بندی درختی)
            $table->integer('user_id'); // کاربر ایجادکننده

            // ایندکس‌ها
            $table->index('position', 'ci_tecat_position_index');
            $table->index('parent', 'ci_tecat_parent_index');
            $table->index('user_id', 'ci_tecat_user_id_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_tecat');
    }
};
