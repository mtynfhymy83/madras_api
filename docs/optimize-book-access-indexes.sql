-- ایندکس‌های بهینه برای بهبود عملکرد userHasBookAccess و getBookDetails
-- اجرا: psql -U myuser -d madras -f docs/optimize-book-access-indexes.sql

-- 1) ایندکس برای products (getBookDetails)
-- این ایندکس احتمالاً وجود دارد، اما مطمئن می‌شویم
CREATE INDEX IF NOT EXISTS idx_products_id_type_deleted 
ON products(id, type, deleted_at) 
WHERE deleted_at IS NULL AND type = 'book';

-- 2) ایندکس برای user_library (userHasBookAccess)
CREATE INDEX IF NOT EXISTS idx_user_library_product_user 
ON user_library(product_id, user_id);

-- 3) ایندکس برای user_subscriptions (userHasBookAccess)
CREATE INDEX IF NOT EXISTS idx_user_subscriptions_user_category_active 
ON user_subscriptions(user_id, category_id, is_active, deleted_at, expires_at)
WHERE is_active = true AND deleted_at IS NULL;

-- 4) ایندکس برای products.category_id (برای JOIN با subscriptions)
CREATE INDEX IF NOT EXISTS idx_products_category_id 
ON products(category_id) 
WHERE type = 'book' AND deleted_at IS NULL;

-- بررسی ایندکس‌های موجود
SELECT 
    schemaname,
    tablename,
    indexname,
    indexdef
FROM pg_indexes
WHERE schemaname = 'public'
  AND tablename IN ('products', 'user_library', 'user_subscriptions')
ORDER BY tablename, indexname;
