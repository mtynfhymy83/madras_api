<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_books', function (Blueprint $table) {
            $table->integer('id')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('book_id');
            $table->unsignedBigInteger('factor_id')->nullable();
            $table->timestamps();
            $table->timestamp('expiremembership')->nullable();

            // Unique constraint: one active access per user-book



        });

        // Partial indexes for optimal query performance (PostgreSQL specific)


    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop view


        // Drop table (indexes will be dropped automatically)
        Schema::dropIfExists('user_books');
    }
};
