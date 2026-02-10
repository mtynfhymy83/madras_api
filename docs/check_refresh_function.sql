-- بررسی وجود function refresh_product_view_data در دیتابیس
-- اجرا: psql -U myuser -d madras -f docs/check_refresh_function.sql

-- بررسی وجود function
SELECT 
    p.proname AS function_name,
    pg_get_function_arguments(p.oid) AS arguments,
    pg_get_functiondef(p.oid) AS definition
FROM pg_proc p
JOIN pg_namespace n ON p.pronamespace = n.oid
WHERE n.nspname = 'public' 
  AND p.proname = 'refresh_product_view_data';

-- اگر چیزی برگرداند، function وجود دارد
-- بررسی trigger های مرتبط
SELECT 
    tgname AS trigger_name,
    tgrelid::regclass AS table_name,
    tgenabled AS enabled
FROM pg_trigger
WHERE tgname LIKE '%view_data%' OR tgname LIKE '%refresh_product_view_data%';

-- برای حذف همه function و trigger ها، فایل drop_refresh_function.sql را اجرا کن:
-- psql -U myuser -d madras -f docs/drop_refresh_function.sql
