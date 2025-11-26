<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ci_mecat', function (Blueprint $table) {
            $table->integer('id'); // int(11) NOT NULL
            $table->integer('position'); // int(11) NOT NULL
            $table->string('name', 255); // varchar(255) NOT NULL
            $table->text('description')->nullable(); // text NULL
            $table->string('pic', 255)->nullable(); // varchar(255) NULL
            $table->string('icon', 30); // varchar(30) NOT NULL
            $table->integer('parent'); // int(11) NOT NULL
            $table->integer('user_id'); // int(11) NOT NULL

            // در صورت نیاز id را primary key تعریف کنید
            $table->primary('id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ci_mecat');
    }
};
