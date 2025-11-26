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
        Schema::create('tecat_data', function (Blueprint $table) {
            $table->bigIncrements('id'); // BIGSERIAL PRIMARY KEY

            $table->integer('tid'); // شناسه مرتبط

            // بدون collation — PostgreSQL نیازی ندارد
            $table->string('data_type', 255);

            $table->integer('data_id'); // آیدی مرتبط با دیتا

            // ایندکس‌ها
            $table->index('tid', 'ci_tecat_data_tid_index');
            $table->index('data_type', 'ci_tecat_data_data_type_index');
            $table->index('data_id', 'ci_tecat_data_data_id_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tecat_data');
    }
};
