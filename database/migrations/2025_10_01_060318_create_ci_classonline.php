<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ci_classonline', function (Blueprint $table) {
            // Primary Key
            $table->bigIncrements('id'); // int(11) AUTO_INCREMENT + PRIMARY KEY

            // Columns
            $table->boolean('published');                   // tinyint(1)
            $table->integer('mecatid');                     // int(11)
            $table->string('title', 255);                   // varchar(255)
            $table->integer('placeid');                     // int(11)
            $table->integer('user_id');                     // int(11)
            $table->string('image', 255);                   // varchar(255)
            $table->text('description');                    // text
            $table->string('teachername', 255);            // varchar(255)
            $table->text('teacherdescription');            // text
            $table->integer('classtime');                   // int(11)
            $table->integer('price');                        // int(11)
            $table->integer('discount');                     // int(11)
            $table->date('startdateclass')->nullable();      // date NULLABLE
            $table->date('regdatedeadline')->nullable();    // date NULLABLE
            $table->date('enddateclass')->nullable();       // date NULLABLE
            $table->text('classlink');                      // text
            $table->bigInteger('createdate');              // bigint(20)
            $table->bigInteger('upddate');                 // bigint(20)
            $table->integer('membership1');                // int(11)
            $table->double('discountmembership1');         // double
            $table->integer('membership3');                // int(11)
            $table->double('discountmembership3');         // double
            $table->integer('membership6');                // int(11)
            $table->double('discountmembership6');         // double
            $table->integer('membership12');               // int(11)
            $table->double('discountmembership12');        // double
            $table->string('pic', 255)->nullable();        // varchar(255) NULLABLE
            $table->string('icon', 255)->nullable();       // varchar(255) NULLABLE
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ci_classonline');
    }
};
