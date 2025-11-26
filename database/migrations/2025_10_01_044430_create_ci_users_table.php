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
        Schema::create('ci_users', function (Blueprint $table) {
            $table->id(); // SERIAL / Postgres identity

            $table->string('username', 50)->nullable();
            $table->string('name', 50)->nullable();
            $table->string('family', 50)->nullable();
            $table->string('displayname', 50)->nullable();

            $table->unsignedTinyInteger('gender')->default(1);
            $table->unsignedTinyInteger('age')->nullable();

            $table->string('email', 255)->nullable();
            $table->string('tel', 20)->nullable();
            $table->string('national_code', 20)->nullable();

            $table->date('birthday')->nullable();

            $table->string('city', 20)->nullable();
            $table->string('state', 20)->nullable();
            $table->string('country', 255)->nullable();
            $table->string('postal_code', 20)->nullable();

            $table->string('address', 1000)->nullable();

            $table->string('password', 255)->nullable();

            $table->text('avatar')->nullable();
            $table->text('cover')->nullable();

            $table->string('register', 10)->default('started');

            $table->boolean('active')->default(true);
            $table->boolean('approved')->default(false);

            $table->string('pending_reason', 1000)->nullable();

            $table->string('level', 255)->default('user');
            $table->string('type', 20)->nullable();

            $table->timestamp('last_seen')->nullable();

            // توجه: نام فیلد date رزرو است → در PG باید با "" نوشته شود
            $table->timestamp('date')->useCurrent()->nullable();

            $table->unsignedInteger('code')->default(0);
            $table->unsignedBigInteger('sendtime')->default(0);
            $table->unsignedInteger('mobilechanged')->default(0);

            $table->boolean('support')->default(false);

            // Critical indexes for authentication and lookups
            $table->unique('username');
            $table->unique('email');
            $table->index('tel');
            $table->index('national_code');
            
            // Indexes for common filters
            $table->index('active');
            $table->index('approved');
            $table->index('level');
            $table->index('type');
            $table->index('code');
            $table->index('date'); // Registration date queries
            $table->index('last_seen'); // Last seen queries
            
            // Composite indexes for common query patterns
            $table->index(['active', 'approved']); // Active and approved users
            $table->index(['level', 'active']); // Users by level and status
        });
    }
       

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_users');
    }
};