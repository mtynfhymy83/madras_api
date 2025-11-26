<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ci_azmoon_result', function (Blueprint $table) {
            // Primary Key - id() automatically creates primary key
            $table->id();

            // Columns
            $table->unsignedInteger('userid');
            $table->unsignedInteger('term')->default(1);
            $table->unsignedInteger('bookid');
            $table->unsignedInteger('azmoon_type');
            $table->unsignedInteger('azmoon_time')->default(0);
            $table->unsignedInteger('azmoon_questions')->default(0);
            $table->unsignedInteger('azmoon_true')->default(0);
            $table->unsignedInteger('azmoon_false')->default(0);
            $table->unsignedInteger('azmoon_none')->default(0);
            $table->decimal('azmoon_result', 5, 2)->default(0); // Better precision than float
            $table->text('azmoon_mahdoode')->nullable();
            $table->timestamp('azmoon_date')->useCurrent();
            $table->decimal('azmoon_final', 10, 2)->default(0);

            // Single column indexes for common filters
            $table->index('userid');
            $table->index('bookid');
            $table->index('term');
            $table->index('azmoon_type');
            $table->index('azmoon_date');
            $table->index('azmoon_final'); // For sorting by final score
            
            // Composite indexes for common query patterns
            $table->index(['userid', 'bookid']); // User's results for a specific book
            $table->index(['userid', 'azmoon_date']); // User's results by date
            $table->index(['userid', 'term']); // User's results by term
            $table->index(['bookid', 'term']); // Results for a book by term
            $table->index(['azmoon_type', 'azmoon_date']); // Results by type and date
            $table->index(['userid', 'bookid', 'term']); // User's results for book and term
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ci_azmoon_result');
    }
};
