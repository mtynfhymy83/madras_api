<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ci_logs', function (Blueprint $table) {
            $table->unsignedInteger('id'); // int(10) UNSIGNED NOT NULL
            $table->string('table', 20)->nullable(); // varchar(20) NULL
            $table->integer('row_id')->nullable(); // int(11) NULL
            $table->string('page_url', 300)->nullable(); // varchar(300) NULL
            $table->string('_is_', 300)->nullable(); // varchar(300) NULL
            $table->string('referer', 300)->nullable(); // varchar(300) NULL
            $table->string('browser', 300)->nullable(); // varchar(300) NULL
            $table->string('mobile', 300)->nullable(); // varchar(300) NULL
            $table->string('robot', 300)->nullable(); // varchar(300) NULL
            $table->string('platform', 300)->nullable(); // varchar(300) NULL
            $table->string('agent', 300)->nullable(); // varchar(300) NULL
            $table->integer('user_id')->nullable(); // int(11) NULL
            $table->string('user_level', 20)->nullable(); // varchar(20) NULL
            $table->string('event', 50)->default('view'); // varchar(50) NOT NULL DEFAULT 'view'
            $table->string('ip', 255)->nullable(); // varchar(255) NULL
            $table->dateTime('date')->nullable(); // datetime NULL
            $table->string('datestr', 20)->nullable(); // varchar(20) NULL

            // در صورت نیاز می‌توانید id را به عنوان primary key تعریف کنید
            $table->primary('id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ci_logs');
    }
};
