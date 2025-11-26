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
        Schema::create('ci_subjalasat', function (Blueprint $table) {
            $table->id(); // bigint(20) primary auto-increment
            $table->integer('jalasatid');     // شناسه جلسه
            $table->integer('bookid');        // شناسه کتاب
            $table->integer('paragraphid');   // شناسه پاراگراف
            $table->text('description');      // توضیحات
            $table->float('duration');        // مدت زمان
            $table->float('startPos');        // موقعیت شروع
            $table->float('endPos');          // موقعیت پایان
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_subjalasat');
    }
};
