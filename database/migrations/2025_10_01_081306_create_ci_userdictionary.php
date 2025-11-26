<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ci_userdictionary', function (Blueprint $table) {
            $table->id();
            $table->integer('fromlang');
            $table->integer('tolang');

            // PostgreSQL خودش UTF8 را پشتیبانی می‌کند — نیازی به charset/collation نیست
            $table->string('kalameh', 255);

            $table->text('translate');

            $table->integer('uid');

            $table->timestamp('regdate')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ci_userdictionary');
    }
};
