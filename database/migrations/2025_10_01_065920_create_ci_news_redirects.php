<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ci_news_redirects', function (Blueprint $table) {
            $table->increments('id'); // int(11) PRIMARY AUTO_INCREMENT
            $table->string('custom_url', 255)->index(); // varchar(255) NOT NULL + Index
            $table->text('actual_url'); // text NOT NULL
            $table->timestamp('created_at')->nullable()->useCurrent(); // timestamp NULL DEFAULT current_timestamp()
            $table->timestamp('updated_at')->nullable()->useCurrentOnUpdate(); // timestamp NULL DEFAULT current_timestamp() ON UPDATE CURRENT_TIMESTAMP()
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ci_news_redirects');
    }
};
