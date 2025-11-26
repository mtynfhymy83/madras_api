<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ci_factors', function (Blueprint $table) {
            $table->id();

            $table->unsignedInteger('user_id')->nullable();
            $table->unsignedTinyInteger('status')->nullable();
            $table->string('state', 1000)->nullable();
            $table->unsignedInteger('cprice')->nullable();
            $table->unsignedInteger('price')->nullable();
            $table->unsignedTinyInteger('discount')->default(0);
            $table->unsignedInteger('discount_id')->nullable();
            $table->unsignedInteger('paid')->default(0);
            $table->string('ref_id', 255)->nullable();
            $table->unsignedInteger('cdate')->nullable();
            $table->unsignedInteger('pdate')->nullable();
            $table->unsignedInteger('owner');
            $table->string('section', 255);
            $table->string('data_id', 255);

            // Indexes
            $table->index('user_id');
            $table->index('status');
            $table->index('paid');
            $table->index('cdate');
            $table->index('pdate');
            $table->index('section');
            $table->index('ref_id');
            $table->index(['user_id', 'status']);
            $table->index(['status', 'paid']);
            $table->index(['user_id', 'cdate']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ci_factors');
    }
};
