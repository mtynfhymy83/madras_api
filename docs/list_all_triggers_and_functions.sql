-- لیست کامل همه trigger ها و function های دیتابیس
-- برای شناسایی موارد اضافی و حذف آن‌ها

-- ============================================
-- 1) لیست همه TRIGGER ها
-- ============================================
SELECT 
    'TRIGGER' AS type,
    tgname AS name,
    tgrelid::regclass AS table_name,
    CASE tgenabled 
        WHEN 'O' THEN 'enabled'
        WHEN 'D' THEN 'disabled'
        ELSE 'unknown'
    END AS status,
    pg_get_triggerdef(oid) AS definition
FROM pg_trigger
WHERE tgisinternal = false  -- فقط trigger های کاربری (نه system triggers)
ORDER BY tgrelid::regclass::text, tgname;

-- ============================================
-- 2) لیست همه FUNCTION ها (user-defined)
-- ============================================
SELECT 
    'FUNCTION' AS type,
    p.proname AS name,
    pg_get_function_arguments(p.oid) AS arguments,
    pg_get_function_result(p.oid) AS return_type,
    CASE p.prokind
        WHEN 'f' THEN 'function'
        WHEN 'p' THEN 'procedure'
        WHEN 'a' THEN 'aggregate'
        WHEN 'w' THEN 'window'
        ELSE 'unknown'
    END AS kind,
    pg_get_functiondef(p.oid) AS definition
FROM pg_proc p
JOIN pg_namespace n ON p.pronamespace = n.oid
WHERE n.nspname = 'public'  -- فقط schema public
  AND p.prokind IN ('f', 'p')  -- فقط function و procedure
ORDER BY p.proname, pg_get_function_arguments(p.oid);

-- ============================================
-- 3) لیست trigger های مرتبط با view_data (مشکوک)
-- ============================================
SELECT 
    'SUSPICIOUS TRIGGER' AS type,
    tgname AS name,
    tgrelid::regclass AS table_name,
    pg_get_triggerdef(oid) AS definition
FROM pg_trigger
WHERE tgisinternal = false
  AND (tgname LIKE '%view_data%' 
       OR tgname LIKE '%refresh%'
       OR tgname LIKE '%product_view%')
ORDER BY tgname;

-- ============================================
-- 4) لیست function های مرتبط با view_data (مشکوک)
-- ============================================
SELECT 
    'SUSPICIOUS FUNCTION' AS type,
    p.proname AS name,
    pg_get_function_arguments(p.oid) AS arguments,
    pg_get_functiondef(p.oid) AS definition
FROM pg_proc p
JOIN pg_namespace n ON p.pronamespace = n.oid
WHERE n.nspname = 'public'
  AND (p.proname LIKE '%view_data%' 
       OR p.proname LIKE '%refresh%'
       OR p.proname LIKE '%product_view%')
ORDER BY p.proname;

-- ============================================
-- 5) خلاصه: تعداد trigger و function ها
-- ============================================
SELECT 
    'SUMMARY' AS type,
    'Total Triggers' AS name,
    COUNT(*)::text AS count
FROM pg_trigger
WHERE tgisinternal = false

UNION ALL

SELECT 
    'SUMMARY' AS type,
    'Total Functions' AS name,
    COUNT(*)::text AS count
FROM pg_proc p
JOIN pg_namespace n ON p.pronamespace = n.oid
WHERE n.nspname = 'public' AND p.prokind IN ('f', 'p');
