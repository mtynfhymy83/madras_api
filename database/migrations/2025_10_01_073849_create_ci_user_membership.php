<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ci_user_membership', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('factor_id')->nullable();
            $table->unsignedInteger('membership_id');
            $table->date('startdate');
            $table->date('enddate');

            // Indexes
            $table->index('user_id');
            $table->index('membership_id');
            $table->index('startdate');
            $table->index('enddate');
            $table->index(['user_id', 'enddate']);
            $table->index(['membership_id', 'enddate']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ci_user_membership');
    }
};
