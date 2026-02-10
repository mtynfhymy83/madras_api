<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('username')->nullable();
            $table->string('displayname', 100)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('tel', 20)->nullable();
            $table->string('password')->nullable();
            $table->string('avatar')->nullable();
            $table->integer('level')->default('1');
            $table->integer('aproved')->default('0');
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            $table->index('username');
            $table->index('tel');
            $table->index('level');
            $table->index('created_at');
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
