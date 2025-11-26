<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ci_discount_used', function (Blueprint $table) {
            $table->id();

            $table->unsignedInteger('user_id');
            $table->unsignedInteger('discount_id');
            $table->unsignedInteger('udate');
            $table->unsignedInteger('factor_id');

            // Indexes
            $table->index('user_id');
            $table->index('discount_id');
            $table->index('factor_id');
            $table->index('udate');
            $table->index(['user_id', 'discount_id']);
            $table->index(['discount_id', 'udate']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ci_discount_used');
    }
};
