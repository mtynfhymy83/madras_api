<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ci_categori', function (Blueprint $table) {
            $table->id();

            $table->unsignedInteger('position')->default(0);
            $table->string('name', 255);

            $table->unsignedInteger('membership1')->default(0);
            $table->decimal('discountmembership1', 5, 2)->default(0);

            $table->unsignedInteger('membership3')->default(0);
            $table->decimal('discountmembership3', 5, 2)->default(0);

            $table->unsignedInteger('membership6')->default(0);
            $table->decimal('discountmembership6', 5, 2)->default(0);

            $table->unsignedInteger('membership12')->default(0);
            $table->decimal('discountmembership12', 5, 2)->default(0);

            $table->text('description')->nullable();
            $table->string('pic', 255)->nullable();
            $table->string('icon', 30);
            $table->unsignedInteger('parent')->default(0);
            $table->string('type', 255)->default('post');

            // Indexes
            $table->index('parent');
            $table->index('type');
            $table->index('position');
            $table->index(['parent', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ci_categori');
    }
};
