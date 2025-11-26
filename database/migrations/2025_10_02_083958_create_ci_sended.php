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
        Schema::create('ci_sended', function (Blueprint $table) {
            $table->increments('id'); // int(11) primary key auto-increment
            $table->string('mobile', 255);
            $table->text('message');
            $table->timestamp('regdate')->useCurrent();
            $table->string('delivery', 255);
            $table->bigInteger('status');
            $table->integer('sended');
            $table->dateTime('sendeddate')->nullable();
            $table->integer('pompid');
            $table->dateTime('receiveddate')->nullable();
            $table->integer('dataid');
            $table->integer('side');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_sended');
    }
};
