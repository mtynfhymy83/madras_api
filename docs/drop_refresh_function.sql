-- حذف function و trigger های refresh_product_view_data از دیتابیس
-- اجرا: psql -U myuser -d madras -f docs/drop_refresh_function.sql
-- یا در pgAdmin: Tools > Query Tool > اجرای این فایل

-- ⚠️ توجه: این اسکریپت function و trigger ها را حذف می‌کند
-- view_data column باقی می‌ماند (فقط function و trigger حذف می‌شوند)

BEGIN;

-- 1) حذف trigger ها (اول trigger ها را حذف می‌کنیم)
DROP TRIGGER IF EXISTS trg_products_view_data ON products;
DROP TRIGGER IF EXISTS trg_product_contributors_view_data ON product_contributors;
DROP TRIGGER IF EXISTS trg_persons_view_data ON persons;
DROP TRIGGER IF EXISTS trg_categories_view_data ON categories;
DROP TRIGGER IF EXISTS trg_publishers_view_data ON publishers;

-- 2) حذف function های trigger
DROP FUNCTION IF EXISTS trg_refresh_product_view_data_on_product() CASCADE;
DROP FUNCTION IF EXISTS trg_refresh_product_view_data_on_contributors() CASCADE;
DROP FUNCTION IF EXISTS trg_refresh_product_view_data_on_person() CASCADE;
DROP FUNCTION IF EXISTS trg_refresh_product_view_data_on_category() CASCADE;
DROP FUNCTION IF EXISTS trg_refresh_product_view_data_on_publisher() CASCADE;

-- 3) حذف function اصلی (همه variant ها)
DROP FUNCTION IF EXISTS refresh_product_view_data(BIGINT) CASCADE;
DROP FUNCTION IF EXISTS fn_refresh_product_view_data(BIGINT) CASCADE;
DROP FUNCTION IF EXISTS fn_refresh_product_view_data(bigint) CASCADE;

COMMIT;

-- بررسی که همه چیز حذف شد
SELECT 
    'Functions remaining:' AS check_type,
    COUNT(*) AS count
FROM pg_proc p
JOIN pg_namespace n ON p.pronamespace = n.oid
WHERE n.nspname = 'public' 
  AND (p.proname LIKE '%refresh_product_view_data%');

SELECT 
    'Triggers remaining:' AS check_type,
    COUNT(*) AS count
FROM pg_trigger
WHERE tgname LIKE '%view_data%' OR tgname LIKE '%refresh_product_view_data%';

-- اگر count = 0 باشد، همه چیز حذف شده است ✅
