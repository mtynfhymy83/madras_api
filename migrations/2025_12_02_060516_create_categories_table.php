<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->string('slug', 280)->unique();
            $table->text('description')->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->onDelete('cascade');

            // Media
            $table->string('image')->nullable();
            $table->string('icon', 50)->nullable();

            // Display
            $table->integer('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->string('type', 50)->default('book'); // book, course, etc.

            // Subscription Plans (از جدول قبلی)
            // حالا در جدول subscription_plans جداگانه

            $table->timestamps();

            // Indexes
            $table->index('slug');
            $table->index('parent_id');
            $table->index('type');
            $table->index(['is_active', 'position']);
            $table->index(['parent_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
