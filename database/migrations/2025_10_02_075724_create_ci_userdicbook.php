<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ci_userdicbook', function (Blueprint $table) {
            $table->id(); // id bigint(20) PRIMARY KEY AUTO_INCREMENT
            $table->integer('bookid'); // bookid int(11) NOT NULL
            $table->integer('dicid');  // dicid int(11) NOT NULL
            $table->integer('uid');    // uid int(11) NOT NULL
            $table->timestamp('regdate')->useCurrent(); // regdate timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ci_userdicbook');
    }
};
