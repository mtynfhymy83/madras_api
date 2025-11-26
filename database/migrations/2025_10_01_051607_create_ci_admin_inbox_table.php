<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ci_admin_inbox', function (Blueprint $table) {
            // Primary key - id() automatically creates primary key, no need for separate primary()
            $table->id();

            // Columns
            $table->string('subject', 255)->nullable();
            $table->text('message');
            $table->string('name', 250);
            $table->string('email', 250);
            $table->text('ansver')->nullable();
            $table->boolean('visited')->default(false);
            $table->dateTime('date')->useCurrent();

            // Single column indexes for common filters
            $table->index('email');
            $table->index('visited');
            $table->index('date'); // For date-based queries and sorting
            
            // Composite indexes for common query patterns
            $table->index(['visited', 'date']); // Unvisited messages sorted by date
            $table->index(['email', 'visited']); // Messages from specific email by status
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ci_admin_inbox');
    }
};
