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
        Schema::create('utm', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id')->nullable();
            $table->string('eitaa_id')->nullable();
            $table->boolean('is_registered')->default(0);
            $table->text('utm')->nullable();
            $table->timestamp('created_at')->useCurrent();

            // Indexes
            $table->index('user_id');
            $table->index('eitaa_id');
            $table->index('is_registered');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('utm');
    }
};


