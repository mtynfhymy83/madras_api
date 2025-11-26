<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ci_discounts', function (Blueprint $table) {
            $table->id();

            $table->string('code', 255)->nullable();
            $table->unsignedTinyInteger('percent')->nullable();
            $table->unsignedInteger('price')->nullable();
            $table->unsignedInteger('category_id')->nullable();
            $table->unsignedInteger('used')->default(0);
            $table->unsignedInteger('factor_id')->nullable();
            $table->unsignedInteger('cdate')->nullable();
            $table->unsignedInteger('udate')->nullable();
            $table->unsignedInteger('expdate')->nullable();
            $table->unsignedInteger('maxallow')->default(0);
            $table->unsignedInteger('fee')->default(0);
            $table->unsignedInteger('bookid')->default(0);
            $table->unsignedInteger('author')->default(0);

            // Indexes
            $table->unique('code');
            $table->index('category_id');
            $table->index('bookid');
            $table->index('author');
            $table->index('expdate');
            $table->index('used');
            $table->index(['code', 'expdate']);
            $table->index(['category_id', 'expdate']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ci_discounts');
    }
};
