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
        Schema::create('user_profiles', function (Blueprint $table) {

            $table->foreignId('user_id')->unique()->constrained('users')->onDelete('cascade');;
            $table->integer('eitaa_id')->nullable();
            $table->string('name')->nullable();
            $table->string('family')->nullable();
            $table->string('gender')->nullable();
            $table->string('age')->nullable();
            $table->string('national_code')->nullable();
            $table->string('birthday')->nullable();
            $table->string('city')->nullable();
            $table->string('country')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('adress')->nullable();
            $table->string('code')->nullable();
            $table->string('sendtime')->nullable();
            $table->timestamps();

            $table->unique('eitaa_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_profiles');
    }
};


