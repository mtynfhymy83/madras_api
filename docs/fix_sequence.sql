-- ========================================================
-- به‌روزرسانی Sequence برای جدول products
-- این مشکل زمانی رخ می‌دهد که داده‌ها به صورت دستی یا از دیتابیس دیگری import شده باشند
-- ========================================================

-- مرحله 1: پیدا کردن نام sequence
-- اگر sequence نام دیگری دارد، ابتدا نام آن را پیدا کنید:
SELECT 
    pg_get_serial_sequence('products', 'id') as sequence_name;

-- مرحله 2: به‌روزرسانی sequence
-- این دستور sequence را به آخرین ID موجود + 1 تنظیم می‌کند
-- (اگر sequence نام دیگری دارد، نام آن را جایگزین کنید)
SELECT setval(
    COALESCE(
        pg_get_serial_sequence('products', 'id'),
        'products_id_seq'
    ),
    (SELECT COALESCE(MAX(id), 1) FROM products),
    true  -- true یعنی nextval() مقدار بعدی را MAX(id) + 1 برمی‌گرداند
);

-- مرحله 3: بررسی نتیجه
-- بررسی آخرین ID موجود
SELECT MAX(id) as max_id FROM products;

-- بررسی مقدار فعلی sequence (بعد از اجرای setval)
SELECT currval(
    COALESCE(
        pg_get_serial_sequence('products', 'id'),
        'products_id_seq'
    )
) as current_sequence_value;

-- ========================================================
-- راه حل جایگزین (اگر sequence وجود ندارد):
-- ========================================================
-- اگر sequence وجود ندارد، می‌توانید آن را ایجاد کنید:
-- 
-- CREATE SEQUENCE IF NOT EXISTS products_id_seq OWNED BY products.id;
-- ALTER TABLE products ALTER COLUMN id SET DEFAULT nextval('products_id_seq');
-- SELECT setval('products_id_seq', (SELECT COALESCE(MAX(id), 1) FROM products), true);
