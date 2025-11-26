<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ci_classaccount', function (Blueprint $table) {
            // Primary Key
            $table->bigIncrements('id'); // bigint(20) AUTO_INCREMENT + PRIMARY KEY

            // Columns
            $table->integer('classonline_id');           // int(11)
            $table->integer('user_id')->default(0);      // int(11) default 0
            $table->string('useronline', 255);           // varchar(255)
            $table->string('userpass', 255);             // varchar(255)
            $table->text('accessslink')->nullable();     // text nullable
            $table->integer('published');                // int(11)
            $table->integer('uid');                      // int(11)
            $table->timestamp('regdate')->useCurrent();  // timestamp DEFAULT current_timestamp()
            $table->integer('factor_id');                // int(11)
            $table->date('upddate')->nullable();         // date nullable
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ci_classaccount');
    }
};
