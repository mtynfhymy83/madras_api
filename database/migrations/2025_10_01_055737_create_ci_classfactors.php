<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ci_classfactors', function (Blueprint $table) {
            // Primary Key
            $table->bigIncrements('id'); // int(11) AUTO_INCREMENT + PRIMARY KEY

            // Columns
            $table->integer('user_id')->nullable();       // int(11) NULLABLE
            $table->tinyInteger('status')->nullable();    // tinyint(4) NULLABLE
            $table->string('state', 1000)->nullable();    // varchar(1000) NULLABLE
            $table->integer('cprice')->nullable();        // int(11) NULLABLE
            $table->integer('price')->nullable();         // int(11) NULLABLE
            $table->tinyInteger('discount')->default(0);  // tinyint(4) DEFAULT 0
            $table->integer('discount_id')->nullable();   // int(11) NULLABLE
            $table->integer('paid')->default(0);          // int(11) DEFAULT 0
            $table->string('ref_id', 255)->nullable();    // varchar(255) NULLABLE
            $table->integer('cdate')->nullable();         // int(11) NULLABLE
            $table->integer('pdate')->nullable();         // int(11) NULLABLE
            $table->integer('owner');                      // int(11) NOT NULL
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ci_classfactors');
    }
};
