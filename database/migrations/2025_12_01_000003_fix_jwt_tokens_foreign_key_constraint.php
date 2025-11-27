<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // For PostgreSQL, drop the constraint using raw SQL to ensure we get the right one
        DB::statement('ALTER TABLE jwt_tokens DROP CONSTRAINT IF EXISTS jwt_tokens_user_id_foreign');
        
        // Also try other possible constraint names
        DB::statement('ALTER TABLE jwt_tokens DROP CONSTRAINT IF EXISTS jwt_tokens_user_id_fkey');

        // Recreate the foreign key pointing to ci_users table
        DB::statement('
            ALTER TABLE jwt_tokens 
            ADD CONSTRAINT jwt_tokens_user_id_foreign 
            FOREIGN KEY (user_id) 
            REFERENCES ci_users(id) 
            ON DELETE CASCADE
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the foreign key pointing to ci_users
        Schema::table('jwt_tokens', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        // Recreate the original foreign key pointing to users table
        Schema::table('jwt_tokens', function (Blueprint $table) {
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
        });
    }
};

