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
        // بهینه‌سازی کوئری user access
        Schema::table('purchases', function (Blueprint $table) {
            $table->index(['user_id', 'book_id', 'status'], 'purchases_user_book_status_idx');
        });

        // بهینه‌سازی کوئری subscription check
        Schema::table('user_subscriptions', function (Blueprint $table) {
            $table->index(['user_id', 'category_id', 'is_active', 'expires_at'], 'user_subs_access_check_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->dropIndex('purchases_user_book_status_idx');
        });

        Schema::table('user_subscriptions', function (Blueprint $table) {
            $table->dropIndex('user_subs_access_check_idx');
        });
    }
};



