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
        Schema::table('ci_users', function (Blueprint $table) {
            $table->string('eitaa_id')->nullable()->unique()->after('id');
            $table->index('eitaa_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ci_users', function (Blueprint $table) {
            $table->dropIndex(['eitaa_id']);
            $table->dropColumn('eitaa_id');
        });
    }
};


