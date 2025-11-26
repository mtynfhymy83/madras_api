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
        Schema::create('ci_supplierrules', function (Blueprint $table) {
            $table->id(); // bigint(20) primary auto_increment
            $table->integer('sup_id');  // شناسه تامین‌کننده
            $table->integer('type_id'); // شناسه نوع
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_supplierrules');
    }
};
