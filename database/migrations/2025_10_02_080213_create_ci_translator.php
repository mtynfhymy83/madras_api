<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ci_translator', function (Blueprint $table) {
            $table->bigIncrements('id');

            // حذف collation — PostgreSQL خودش UTF-8 است
            $table->string('title', 255);

            $table->integer('uid');
            $table->timestamp('regdate')->useCurrent();

            // ایندکس‌ها
            $table->index('uid', 'ci_translator_uid_index');
            $table->index('title', 'ci_translator_title_index');
            $table->index('regdate', 'ci_translator_regdate_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ci_translator');
    }
};
