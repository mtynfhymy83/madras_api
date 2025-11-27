<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Use a DO block to safely find and drop the constraint pointing to 'users'
        DB::statement("
            DO \$\$
            DECLARE
                constraint_name text;
            BEGIN
                SELECT conname INTO constraint_name
                FROM pg_constraint
                WHERE conrelid::regclass::text = 'jwt_tokens'
                AND contype = 'f'
                AND confrelid::regclass::text = 'users';
                
                IF constraint_name IS NOT NULL THEN
                    EXECUTE format('ALTER TABLE jwt_tokens DROP CONSTRAINT %I', constraint_name);
                END IF;
            END \$\$;
        ");
        
        // Drop any existing constraint with the name we want to use
        DB::statement("ALTER TABLE jwt_tokens DROP CONSTRAINT IF EXISTS jwt_tokens_user_id_foreign");
        
        // Create new constraint pointing to ci_users
        DB::statement("
            ALTER TABLE jwt_tokens 
            ADD CONSTRAINT jwt_tokens_user_id_foreign 
            FOREIGN KEY (user_id) 
            REFERENCES ci_users(id) 
            ON DELETE CASCADE
        ");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE jwt_tokens DROP CONSTRAINT IF EXISTS jwt_tokens_user_id_foreign");
    }
};
