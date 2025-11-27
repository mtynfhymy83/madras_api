-- Fix JWT Tokens Foreign Key Constraint
-- Run this SQL directly in your PostgreSQL database

-- Step 1: Find and drop the existing constraint pointing to 'users'
DO $$
DECLARE
    constraint_name text;
BEGIN
    -- Find the constraint name
    SELECT conname INTO constraint_name
    FROM pg_constraint
    WHERE conrelid::regclass::text = 'jwt_tokens'
    AND contype = 'f'
    AND confrelid::regclass::text = 'users';
    
    -- Drop it if found
    IF constraint_name IS NOT NULL THEN
        EXECUTE format('ALTER TABLE jwt_tokens DROP CONSTRAINT %I', constraint_name);
        RAISE NOTICE 'Dropped constraint: %', constraint_name;
    END IF;
END $$;

-- Step 2: Create new constraint pointing to ci_users
ALTER TABLE jwt_tokens 
ADD CONSTRAINT jwt_tokens_user_id_foreign 
FOREIGN KEY (user_id) 
REFERENCES ci_users(id) 
ON DELETE CASCADE;

-- Verify the constraint
SELECT 
    conname as constraint_name,
    conrelid::regclass::text as table_name,
    confrelid::regclass::text as referenced_table
FROM pg_constraint
WHERE conrelid::regclass::text = 'jwt_tokens'
AND contype = 'f';

