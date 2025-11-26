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
        Schema::create('jwt_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('refresh_token', 255)->unique();
            $table->string('mini_app_uuid');
            $table->json('roles')->nullable();
            $table->json('permissions')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('last_used_at')->nullable();
            $table->string('device_info')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'mini_app_uuid']);
            $table->index('refresh_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jwt_tokens');
    }
};