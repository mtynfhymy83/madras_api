<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique(); // 'super_admin', 'admin', 'operator', 'user'
            $table->string('display_name', 100);
            $table->text('description')->nullable();
            $table->integer('priority')->default(0); // برای ترتیب سلسله مراتبی (هرچه بیشتر، قدرت بیشتر)
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index('name');
            $table->index('priority');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
