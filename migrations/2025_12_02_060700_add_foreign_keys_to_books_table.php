<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Books table foreign keys
        Schema::table('books', function (Blueprint $table) {
            if (Schema::hasTable('publishers')) {
                $table->foreign('publisher_id')
                    ->references('id')
                    ->on('publishers')
                    ->onDelete('set null');
            }
            
            if (Schema::hasTable('categories')) {
                $table->foreign('category')
                    ->references('id')
                    ->on('categories')
                    ->onDelete('set null');
            }
        });

        // Subscription plans foreign keys
        if (Schema::hasTable('subscription_plans') && Schema::hasTable('categories')) {
            Schema::table('subscription_plans', function (Blueprint $table) {
                $table->foreign('category_id')
                    ->references('id')
                    ->on('categories')
                    ->onDelete('cascade');
            });
        }

        // User subscriptions foreign keys
        if (Schema::hasTable('user_subscriptions')) {
            Schema::table('user_subscriptions', function (Blueprint $table) {
                if (Schema::hasTable('categories')) {
                    $table->foreign('category_id')
                        ->references('id')
                        ->on('categories')
                        ->onDelete('cascade');
                }
                
                if (Schema::hasTable('subscription_plans')) {
                    $table->foreign('subscription_plan_id')
                        ->references('id')
                        ->on('subscription_plans')
                        ->onDelete('cascade');
                }
                
                if (Schema::hasTable('purchases')) {
                    $table->foreign('purchase_id')
                        ->references('id')
                        ->on('purchases')
                        ->onDelete('set null');
                }
            });
        }

        // Subscription logs foreign keys
        if (Schema::hasTable('subscription_logs') && Schema::hasTable('user_subscriptions')) {
            Schema::table('subscription_logs', function (Blueprint $table) {
                $table->foreign('user_subscription_id')
                    ->references('id')
                    ->on('user_subscriptions')
                    ->onDelete('cascade');
            });
        }

        // User library foreign keys - removed (simplified structure)
    }

    public function down(): void
    {
        // User library foreign keys - removed (simplified structure)

        if (Schema::hasTable('subscription_logs')) {
            Schema::table('subscription_logs', function (Blueprint $table) {
                $table->dropForeign(['user_subscription_id']);
            });
        }

        if (Schema::hasTable('user_subscriptions')) {
            Schema::table('user_subscriptions', function (Blueprint $table) {
                $table->dropForeign(['category_id']);
                $table->dropForeign(['subscription_plan_id']);
                $table->dropForeign(['purchase_id']);
            });
        }

        if (Schema::hasTable('subscription_plans')) {
            Schema::table('subscription_plans', function (Blueprint $table) {
                $table->dropForeign(['category_id']);
            });
        }

        Schema::table('books', function (Blueprint $table) {
            $table->dropForeign(['publisher_id']);
            $table->dropForeign(['primary_category_id']);
        });
    }
};

