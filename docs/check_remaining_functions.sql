-- بررسی function های باقی‌مانده مرتبط با refresh_product_view_data
SELECT 
    p.proname AS function_name,
    pg_get_function_arguments(p.oid) AS arguments,
    pg_get_functiondef(p.oid) AS definition
FROM pg_proc p
JOIN pg_namespace n ON p.pronamespace = n.oid
WHERE n.nspname = 'public' 
  AND (p.proname LIKE '%refresh_product_view_data%' OR p.proname LIKE '%view_data%')
ORDER BY p.proname;
