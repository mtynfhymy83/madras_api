<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ci_advertise', function (Blueprint $table) {
            // Primary key - id() automatically creates primary key, no need for separate primary() or index()
            $table->id();

            // Columns
            $table->string('title', 255);
            $table->string('link', 999);
            $table->text('description');
            $table->string('image', 255);
            $table->timestamp('regdate')->useCurrent();
            $table->unsignedInteger('priority')->default(0);
            $table->unsignedInteger('showed')->default(0);
            $table->unsignedInteger('user_id')->nullable();
            $table->string('section', 255)->nullable();

            // Single column indexes for common filters
            $table->index('user_id');
            $table->index('section');
            $table->index('priority');
            $table->index('regdate');
            $table->index('showed');
            
            // Composite indexes for common query patterns
            $table->index(['section', 'priority']); // Advertisements by section ordered by priority
            $table->index(['user_id', 'regdate']); // User's advertisements by date
            $table->index(['section', 'showed']); // Advertisements by section and view count
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ci_advertise');
    }
};
