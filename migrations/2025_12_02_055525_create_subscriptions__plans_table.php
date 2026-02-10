<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // طرح‌های اشتراک برای هر دسته‌بندی
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('category_id'); // Foreign key will be added later

            $table->integer('duration_months'); // مدت زمان به ماه (1, 3, 6, 12)
            $table->decimal('price', 12, 2); // قیمت اشتراک
            $table->decimal('discount_percentage', 5, 2)->default(0); // درصد تخفیف

            $table->boolean('is_active')->default(true);
            $table->integer('priority')->default(0); // برای ترتیب نمایش

            $table->timestamps();

            // Indexes
            $table->index('category_id');
            $table->index(['category_id', 'is_active']);
            $table->unique(['category_id', 'duration_months']);
        });

        // اشتراک‌های فعال کاربران
        Schema::create('user_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // users table exists
            $table->unsignedBigInteger('category_id'); // Foreign key will be added later
            $table->unsignedBigInteger('subscription_plan_id'); // Foreign key will be added later
            $table->unsignedBigInteger('purchase_id')->nullable(); // Foreign key will be added later

            // Subscription Period
            $table->timestamp('starts_at');
            $table->timestamp('expires_at');
            $table->boolean('is_active')->default(true);
            $table->boolean('auto_renew')->default(false);

            // Payment Info
            $table->decimal('amount_paid', 12, 2);
            $table->decimal('discount_applied', 12, 2)->default(0);

            $table->timestamps();
            $table->softDeletes();

            // Indexes for Performance
            $table->index('user_id');
            $table->index('category_id');
            $table->index('expires_at');
            $table->index(['user_id', 'is_active']);
            $table->index(['user_id', 'category_id', 'is_active']);
            $table->index(['expires_at', 'is_active']);
        });

        // لاگ تغییرات اشتراک (برای تاریخچه)
        Schema::create('subscription_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_subscription_id'); // Foreign key will be added later
            $table->enum('action', ['created', 'renewed', 'expired', 'cancelled', 'suspended']);
            $table->text('notes')->nullable();
            $table->timestamp('logged_at');

            $table->index('user_subscription_id');
            $table->index('logged_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_logs');
        Schema::dropIfExists('user_subscriptions');
        Schema::dropIfExists('subscription_plans');
    }
};
