-- ========================================================
-- توابع تریگر برای view_data در جدول products
-- این فایل را می‌توانید در دیتابیس دیگر اجرا کنید
-- ========================================================

-- 1) اضافه کردن ستون view_data (اگر وجود ندارد)
ALTER TABLE products ADD COLUMN IF NOT EXISTS view_data JSONB DEFAULT '{}';
CREATE INDEX IF NOT EXISTS idx_products_view_data ON products USING GIN (view_data);

-- ========================================================
-- 2) تابع اصلی: refresh view_data برای یک محصول
-- ========================================================
CREATE OR REPLACE FUNCTION refresh_product_view_data(p_product_id BIGINT)
RETURNS void AS $$
BEGIN
  UPDATE products p
  SET view_data = (
    SELECT jsonb_build_object(
      'authors', COALESCE(
        (SELECT string_agg(per.full_name, ', ' ORDER BY per.full_name)
         FROM product_contributors pc JOIN persons per ON per.id = pc.person_id
         WHERE pc.product_id = p.id AND pc.role = 'author'), ''),
      'translators', COALESCE(
        (SELECT string_agg(per.full_name, ', ' ORDER BY per.full_name)
         FROM product_contributors pc JOIN persons per ON per.id = pc.person_id
         WHERE pc.product_id = p.id AND pc.role = 'translator'), ''),
      'category', (SELECT jsonb_build_object('id', c.id, 'title', c.title, 'path', c.full_path)
                   FROM categories c WHERE c.id = p.category_id),
      'publisher', (SELECT jsonb_build_object('id', pub.id, 'title', pub.title)
                    FROM publishers pub WHERE pub.id = p.publisher_id)
    )
  )
  WHERE p.id = p_product_id AND p.type = 'book';
END;
$$ LANGUAGE plpgsql;

-- ========================================================
-- 3) تریگر: وقتی category_id یا publisher_id محصول تغییر می‌کند
-- ========================================================
CREATE OR REPLACE FUNCTION trg_refresh_product_view_data_on_product()
RETURNS TRIGGER AS $$
BEGIN
  IF NEW.type = 'book' THEN
    IF TG_OP = 'INSERT' THEN
      PERFORM refresh_product_view_data(NEW.id);
    ELSIF OLD.category_id IS DISTINCT FROM NEW.category_id OR OLD.publisher_id IS DISTINCT FROM NEW.publisher_id THEN
      PERFORM refresh_product_view_data(NEW.id);
    END IF;
  END IF;
  RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_products_view_data ON products;
CREATE TRIGGER trg_products_view_data
  AFTER INSERT OR UPDATE OF category_id, publisher_id ON products
  FOR EACH ROW EXECUTE FUNCTION trg_refresh_product_view_data_on_product();

-- ========================================================
-- 4) تریگر: وقتی product_contributors تغییر می‌کند
-- ========================================================
CREATE OR REPLACE FUNCTION trg_refresh_product_view_data_on_contributors()
RETURNS TRIGGER AS $$
DECLARE
  pid BIGINT;
BEGIN
  IF TG_OP = 'DELETE' THEN pid := OLD.product_id; ELSE pid := NEW.product_id; END IF;
  PERFORM refresh_product_view_data(pid);
  IF TG_OP = 'DELETE' THEN RETURN OLD; ELSE RETURN NEW; END IF;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_product_contributors_view_data ON product_contributors;
CREATE TRIGGER trg_product_contributors_view_data
  AFTER INSERT OR UPDATE OR DELETE ON product_contributors
  FOR EACH ROW EXECUTE FUNCTION trg_refresh_product_view_data_on_contributors();

-- ========================================================
-- 5) تریگر: وقتی full_name شخص تغییر می‌کند
-- ========================================================
CREATE OR REPLACE FUNCTION trg_refresh_product_view_data_on_person()
RETURNS TRIGGER AS $$
BEGIN
  IF OLD.full_name IS DISTINCT FROM NEW.full_name THEN
    PERFORM refresh_product_view_data(pc.product_id)
    FROM product_contributors pc
    WHERE pc.person_id = NEW.id;
  END IF;
  RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_persons_view_data ON persons;
CREATE TRIGGER trg_persons_view_data
  AFTER UPDATE OF full_name ON persons
  FOR EACH ROW EXECUTE FUNCTION trg_refresh_product_view_data_on_person();

-- ========================================================
-- 6) تریگر: وقتی title یا full_path دسته‌بندی تغییر می‌کند
-- ========================================================
CREATE OR REPLACE FUNCTION trg_refresh_product_view_data_on_category()
RETURNS TRIGGER AS $$
BEGIN
  IF OLD.title IS DISTINCT FROM NEW.title OR OLD.full_path IS DISTINCT FROM NEW.full_path THEN
    PERFORM refresh_product_view_data(p.id)
    FROM products p
    WHERE p.category_id = NEW.id AND p.type = 'book';
  END IF;
  RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_categories_view_data ON categories;
CREATE TRIGGER trg_categories_view_data
  AFTER UPDATE OF title, full_path ON categories
  FOR EACH ROW EXECUTE FUNCTION trg_refresh_product_view_data_on_category();

-- ========================================================
-- 7) تریگر: وقتی title ناشر تغییر می‌کند
-- ========================================================
CREATE OR REPLACE FUNCTION trg_refresh_product_view_data_on_publisher()
RETURNS TRIGGER AS $$
BEGIN
  IF OLD.title IS DISTINCT FROM NEW.title THEN
    PERFORM refresh_product_view_data(p.id)
    FROM products p
    WHERE p.publisher_id = NEW.id AND p.type = 'book';
  END IF;
  RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_publishers_view_data ON publishers;
CREATE TRIGGER trg_publishers_view_data
  AFTER UPDATE OF title ON publishers
  FOR EACH ROW EXECUTE FUNCTION trg_refresh_product_view_data_on_publisher();

-- ========================================================
-- توضیحات:
-- این تریگرها به صورت خودکار view_data را در جدول products به‌روز می‌کنند
-- وقتی که:
-- 1. محصول جدید اضافه می‌شود یا category_id/publisher_id تغییر می‌کند
-- 2. product_contributors تغییر می‌کند (افزودن/حذف نویسنده یا مترجم)
-- 3. نام شخص (author/translator) تغییر می‌کند
-- 4. عنوان یا مسیر دسته‌بندی تغییر می‌کند
-- 5. عنوان ناشر تغییر می‌کند
-- ========================================================
