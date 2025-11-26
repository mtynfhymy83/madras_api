<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ci_captcha', function (Blueprint $table) {
            // Primary Key - bigIncrements automatically creates primary key
            $table->bigIncrements('captcha_id');

            // Columns
            $table->unsignedInteger('captcha_time');
            $table->string('ip_address', 45);
            $table->string('word', 20);

            // Single column indexes for common queries
            $table->index('ip_address'); // For rate limiting and finding captchas by IP
            $table->index('captcha_time'); // For cleanup of expired captchas
            
            // Composite indexes for common query patterns
            $table->index(['ip_address', 'captcha_time']); // Recent captchas by IP (for rate limiting)
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ci_captcha');
    }
};
