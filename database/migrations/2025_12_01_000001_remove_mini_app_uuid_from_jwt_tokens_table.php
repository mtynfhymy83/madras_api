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
        Schema::table('jwt_tokens', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'mini_app_uuid']);
            $table->dropColumn('mini_app_uuid');
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('jwt_tokens', function (Blueprint $table) {
            $table->string('mini_app_uuid')->after('refresh_token');
            $table->dropIndex(['user_id']);
            $table->index(['user_id', 'mini_app_uuid']);
        });
    }
};


