DROP SCHEMA public CASCADE;
CREATE SCHEMA public;

-- Extensions
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "pgcrypto";

-- ========================================================
-- 1. Users & Authentication
-- ========================================================

CREATE TABLE users (
    id BIGSERIAL PRIMARY KEY,
    old_id BIGINT UNIQUE,
    
    username VARCHAR(100) UNIQUE,
    mobile VARCHAR(20) UNIQUE,
    email VARCHAR(255) UNIQUE,
    password VARCHAR(255),
    
    role VARCHAR(20) DEFAULT 'user',
    status VARCHAR(20) DEFAULT 'active',
    
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW(),
    deleted_at TIMESTAMPTZ
);

CREATE INDEX idx_users_mobile ON users(mobile) WHERE deleted_at IS NULL;
CREATE INDEX idx_users_email ON users(email) WHERE deleted_at IS NULL;
CREATE INDEX idx_users_role ON users(role) WHERE deleted_at IS NULL;

CREATE TABLE user_profiles (
    user_id BIGINT PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
    
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    full_name VARCHAR(255),
    eitaa_id VARCHAR(50) UNIQUE,
    
    national_code VARCHAR(20),
    gender SMALLINT DEFAULT 1,
    birth_date DATE,
    
    country VARCHAR(100) DEFAULT 'Iran',
    province VARCHAR(100),
    city VARCHAR(100),
    postal_code VARCHAR(20),
    address TEXT,
    
    avatar_path VARCHAR(500),
    cover_path VARCHAR(500),
    
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX idx_profiles_eitaa_id ON user_profiles(eitaa_id);

CREATE TABLE user_devices (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT REFERENCES users(id) ON DELETE CASCADE,
    device_name VARCHAR(255),
    os_version VARCHAR(100),
    app_version VARCHAR(50),
    device_token VARCHAR(500),
    device_id VARCHAR(255),
    last_active_at TIMESTAMPTZ DEFAULT NOW(),
    created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX idx_user_devices_user ON user_devices(user_id);
CREATE INDEX idx_user_devices_token ON user_devices(device_token);

-- Refresh Tokens for JWT
CREATE TABLE refresh_tokens (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT REFERENCES users(id) ON DELETE CASCADE,
    token VARCHAR(500) NOT NULL UNIQUE,
    device_id VARCHAR(255),
    expires_at TIMESTAMPTZ NOT NULL,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX idx_refresh_tokens_user ON refresh_tokens(user_id);
CREATE INDEX idx_refresh_tokens_expires ON refresh_tokens(expires_at);

-- ========================================================
-- 2. Base Tables (Dependencies)
-- ========================================================

CREATE TABLE publishers (
    id BIGSERIAL PRIMARY KEY,
    old_id BIGINT UNIQUE,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE,
    logo VARCHAR(500),
    created_at TIMESTAMPTZ DEFAULT NOW(),
    deleted_at TIMESTAMPTZ
);

CREATE INDEX idx_publishers_slug ON publishers(slug);

CREATE TABLE categories (
    id BIGSERIAL PRIMARY KEY,
    old_id BIGINT UNIQUE,
    parent_id BIGINT REFERENCES categories(id) ON DELETE SET NULL,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255),
    icon VARCHAR(255),
    full_path VARCHAR(500), -- Materialized path: "1/5/12"
    depth SMALLINT DEFAULT 0,
    type VARCHAR(20) DEFAULT 'book',
    is_active BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0
);

CREATE INDEX idx_categories_parent ON categories(parent_id);
CREATE INDEX idx_categories_slug ON categories(slug);
CREATE INDEX idx_categories_path ON categories(full_path);
CREATE INDEX idx_categories_type ON categories(type, is_active);

CREATE TABLE persons (
    id BIGSERIAL PRIMARY KEY,
    old_id BIGINT,
    full_name VARCHAR(255) NOT NULL,
    slug VARCHAR(255),
    avatar VARCHAR(500),
    type VARCHAR(50), -- author, translator, narrator, teacher
    bio TEXT,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    CONSTRAINT uq_persons_old_id_type UNIQUE (old_id, type)
);

CREATE INDEX idx_persons_slug ON persons(slug);
CREATE INDEX idx_persons_type ON persons(type);

-- ========================================================
-- 3. Products (Books, Courses, Audiobooks)
-- ========================================================

CREATE TABLE products (
    id BIGSERIAL PRIMARY KEY,
    old_id BIGINT,
    
    type VARCHAR(20) NOT NULL, -- 'book', 'course', 'audiobook'
    title VARCHAR(500) NOT NULL,
    slug VARCHAR(500) UNIQUE,
    status SMALLINT DEFAULT 1, -- 0: Draft, 1: Published, 2: Hidden
    
    price DECIMAL(15,0) DEFAULT 0,
    price_with_discount DECIMAL(15,0) DEFAULT 0,
    
    publisher_id BIGINT REFERENCES publishers(id) ON DELETE SET NULL,
    category_id BIGINT REFERENCES categories(id) ON DELETE SET NULL,
    
    cover_image VARCHAR(1000),
    demo_file VARCHAR(1000),
    description TEXT,
    
    -- Flexible attributes (pages, isbn, file_size, etc.)
    attributes JSONB DEFAULT '{}',
    
    -- View cache for book details API (authors, translators, category, publisher). Kept in sync by triggers.
    view_data JSONB DEFAULT '{}',
    
    -- Denormalized stats (updated via triggers or background jobs)
    view_count BIGINT DEFAULT 0,
    sale_count BIGINT DEFAULT 0,
    rate_avg DECIMAL(3, 2) DEFAULT 0,
    rate_count INT DEFAULT 0,
    
    -- Full-text search vector
    search_vector TSVECTOR,
    
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW(),
    deleted_at TIMESTAMPTZ,
    
    CONSTRAINT uq_products_old_id_type UNIQUE (old_id, type)
);

-- Performance indexes
CREATE INDEX idx_products_type_status ON products(type, status) WHERE deleted_at IS NULL;
CREATE INDEX idx_products_category ON products(category_id) WHERE deleted_at IS NULL;
CREATE INDEX idx_products_publisher ON products(publisher_id) WHERE deleted_at IS NULL;
CREATE INDEX idx_products_slug ON products(slug);
CREATE INDEX idx_products_attributes ON products USING GIN (attributes);
CREATE INDEX idx_products_view_data ON products USING GIN (view_data);
CREATE INDEX idx_products_search ON products USING GIN (search_vector);
CREATE INDEX idx_products_popular ON products(view_count DESC, sale_count DESC) WHERE status = 1 AND deleted_at IS NULL;
CREATE INDEX idx_products_newest ON products(created_at DESC) WHERE status = 1 AND deleted_at IS NULL;

-- Trigger for search vector update
CREATE OR REPLACE FUNCTION products_search_trigger() RETURNS trigger AS $$
BEGIN
    NEW.search_vector := 
        setweight(to_tsvector('simple', COALESCE(NEW.title, '')), 'A') ||
        setweight(to_tsvector('simple', COALESCE(NEW.description, '')), 'B');
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_products_search
BEFORE INSERT OR UPDATE OF title, description ON products
FOR EACH ROW EXECUTE FUNCTION products_search_trigger();

-- ========================================================
-- 4. Product Contents & Contributors
-- ========================================================

CREATE TABLE product_contents (
    id BIGSERIAL PRIMARY KEY,
    product_id BIGINT NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    old_id BIGINT,
    
    parent_id BIGINT REFERENCES product_contents(id) ON DELETE CASCADE,
    title VARCHAR(255),
    sort_order INT DEFAULT 0,
    page_number INT, -- book page number for navigation
    
    content_type VARCHAR(20) DEFAULT 'text', -- text, audio, video, pdf
    body TEXT,
    file_path VARCHAR(1000),
    duration INT, -- seconds for audio/video
    
    is_free BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX idx_contents_product ON product_contents(product_id, sort_order);
CREATE INDEX idx_contents_product_page ON product_contents(product_id, page_number) WHERE page_number IS NOT NULL;

CREATE TABLE product_contributors (
    product_id BIGINT REFERENCES products(id) ON DELETE CASCADE,
    person_id BIGINT REFERENCES persons(id) ON DELETE CASCADE,
    role VARCHAR(50), -- author, translator, narrator, editor
    PRIMARY KEY (product_id, person_id, role)
);

CREATE INDEX idx_contributors_person ON product_contributors(person_id);

-- Product meta (key-value from ci_post_meta)
CREATE TABLE product_meta (
    id BIGSERIAL PRIMARY KEY,
    product_id BIGINT NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    meta_key VARCHAR(200) NOT NULL,
    meta_value TEXT,
    UNIQUE (product_id, meta_key)
);
CREATE INDEX idx_product_meta_product ON product_meta(product_id);
CREATE INDEX idx_product_meta_key ON product_meta(meta_key);

-- ========================================================
-- 5. Orders & Transactions
-- ========================================================

CREATE TABLE orders (
    id BIGSERIAL PRIMARY KEY,
    old_id BIGINT UNIQUE,
    user_id BIGINT REFERENCES users(id) ON DELETE SET NULL,
    
    total_price DECIMAL(15, 0) NOT NULL DEFAULT 0,
    discount_amount DECIMAL(15, 0) DEFAULT 0,
    final_price DECIMAL(15, 0) NOT NULL DEFAULT 0,
    
    coupon_code VARCHAR(50),
    status VARCHAR(20) DEFAULT 'pending', -- pending, paid, completed, cancelled
    
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX idx_orders_user ON orders(user_id);
CREATE INDEX idx_orders_status ON orders(status, created_at DESC);

-- transactions: ساختار مطابق ci_factors (فاکتور/تراکنش پرداخت)
CREATE TABLE transactions (
    id BIGINT PRIMARY KEY,
    user_id BIGINT REFERENCES users(id) ON DELETE SET NULL,
    
    status SMALLINT NULL,                    -- وضعیت پرداخت
    state VARCHAR(1000) NULL,                -- پیام متنی وضعیت سفارش
    cprice INTEGER NULL,                     -- قیمت بدون تخفیف
    price INTEGER NULL,                      -- قیمت کل قابل پرداخت
    discount SMALLINT NOT NULL DEFAULT 0,    -- مقدار تخفیف لحاظ شده
    discount_id INTEGER NULL,                -- شناسه کد تخفیف
    paid INTEGER NOT NULL DEFAULT 0,         -- مبلغ پرداخت شده
    ref_id VARCHAR(255) NULL,                -- شماره پیگیری برگشتی از بانک
    cdate INTEGER NULL,                      -- تاریخ ایجاد صورتحساب (unix timestamp)
    pdate INTEGER NULL,                      -- تاریخ پرداخت (unix timestamp)
    owner INTEGER NOT NULL,                 -- مالک
    section VARCHAR(255) NOT NULL,           -- بخش
    data_id VARCHAR(255) NOT NULL            -- شناسه داده
);

CREATE SEQUENCE IF NOT EXISTS transactions_id_seq OWNED BY transactions.id;
ALTER TABLE transactions ALTER COLUMN id SET DEFAULT nextval('transactions_id_seq');

CREATE INDEX idx_transactions_ref ON transactions(ref_id);
CREATE INDEX idx_transactions_user ON transactions(user_id);
CREATE INDEX idx_transactions_status ON transactions(status);
CREATE INDEX idx_transactions_cdate ON transactions(cdate);

CREATE TABLE order_items (
    id BIGSERIAL PRIMARY KEY,
    order_id BIGINT REFERENCES orders(id) ON DELETE CASCADE,
    product_id BIGINT REFERENCES products(id) ON DELETE SET NULL,
    
    unit_price DECIMAL(15, 0) NOT NULL,
    final_price DECIMAL(15, 0) NOT NULL
);

CREATE INDEX idx_order_items_order ON order_items(order_id);
CREATE INDEX idx_order_items_product ON order_items(product_id);

-- ========================================================
-- 6. User Library & Reading Progress
-- ========================================================

CREATE TABLE user_library (
    user_id BIGINT REFERENCES users(id) ON DELETE CASCADE,
    product_id BIGINT REFERENCES products(id) ON DELETE CASCADE,
    
    obtained_at TIMESTAMPTZ DEFAULT NOW(),
    source VARCHAR(20) DEFAULT 'purchase', -- purchase, gift, subscription, manual
    expires_at TIMESTAMPTZ, -- NULL = permanent
    
    PRIMARY KEY (user_id, product_id)
);

CREATE INDEX idx_user_library_user ON user_library(user_id);
CREATE INDEX idx_user_library_product ON user_library(product_id);

CREATE TABLE reading_progress (
    user_id BIGINT REFERENCES users(id) ON DELETE CASCADE,
    product_id BIGINT REFERENCES products(id) ON DELETE CASCADE,
    
    current_page INT DEFAULT 0,
    total_pages INT DEFAULT 0,
    percentage SMALLINT DEFAULT 0,
    current_position VARCHAR(100), -- For ebook: CFI, for audio: timestamp
    
    started_at TIMESTAMPTZ DEFAULT NOW(),
    last_read_at TIMESTAMPTZ DEFAULT NOW(),
    finished_at TIMESTAMPTZ,
    
    PRIMARY KEY (user_id, product_id)
);

CREATE INDEX idx_reading_progress_recent ON reading_progress(user_id, last_read_at DESC);

-- ========================================================
-- 7. Reviews & Ratings
-- ========================================================

CREATE TABLE reviews (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT REFERENCES users(id) ON DELETE CASCADE,
    product_id BIGINT REFERENCES products(id) ON DELETE CASCADE,
    
    rating SMALLINT NOT NULL CHECK (rating BETWEEN 1 AND 5),
    title VARCHAR(255),
    comment TEXT,
    
    is_approved BOOLEAN DEFAULT FALSE,
    is_featured BOOLEAN DEFAULT FALSE,
    helpful_count INT DEFAULT 0,
    
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW(),
    
    UNIQUE(user_id, product_id)
);

CREATE INDEX idx_reviews_product ON reviews(product_id, is_approved, created_at DESC);
CREATE INDEX idx_reviews_user ON reviews(user_id);

-- ========================================================
-- 8. Bookmarks & Highlights
-- ========================================================

CREATE TABLE bookmarks (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT REFERENCES users(id) ON DELETE CASCADE,
    product_id BIGINT REFERENCES products(id) ON DELETE CASCADE,
    
    page_number INT,
    position VARCHAR(100), -- CFI for ebook
    title VARCHAR(255),
    note TEXT,
    color VARCHAR(20) DEFAULT 'yellow',
    
    created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX idx_bookmarks_user_product ON bookmarks(user_id, product_id);

CREATE TABLE highlights (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT REFERENCES users(id) ON DELETE CASCADE,
    product_id BIGINT REFERENCES products(id) ON DELETE CASCADE,
    
    start_position VARCHAR(100),
    end_position VARCHAR(100),
    selected_text TEXT,
    note TEXT,
    color VARCHAR(20) DEFAULT 'yellow',
    
    created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX idx_highlights_user_product ON highlights(user_id, product_id);

-- ========================================================
-- 9. Coupons (ساختار مطابق ci_discounts) & discount_used & Subscriptions
-- ========================================================

-- coupons: ساختار مطابق ci_discounts
CREATE TABLE coupons (
    id BIGINT PRIMARY KEY,
    code VARCHAR(255) NULL,
    percent SMALLINT NULL,              -- درصد تخفیف
    price VARCHAR(255) NULL,            -- مبلغ ثابت تخفیف
    category_id INTEGER NULL,           -- محدود به دسته (منفی: کتاب خاص -1، عمومی -2)
    used INT NOT NULL DEFAULT 0,        -- تعداد استفاده
    factor_id BIGINT NULL,              -- آخرین فاکتور استفاده
    cdate INTEGER NULL,                 -- تاریخ ایجاد (unix)
    udate INTEGER NULL,                 -- تاریخ به‌روزرسانی (unix)
    expdate INTEGER NULL,               -- تاریخ انقضا (unix)
    maxallow INT NOT NULL DEFAULT 0,    -- حداکثر تعداد استفاده مجاز
    fee INT NOT NULL DEFAULT 0,         -- کارمزد
    bookid INT NOT NULL DEFAULT 0,      -- محدود به کتاب (0 = خیر)
    author INT NOT NULL DEFAULT 0       -- محدود به نویسنده/مالک
);

CREATE SEQUENCE IF NOT EXISTS coupons_id_seq OWNED BY coupons.id;
CREATE INDEX idx_coupons_code ON coupons(code);
CREATE INDEX idx_coupons_category ON coupons(category_id);
CREATE INDEX idx_coupons_expdate ON coupons(expdate);

-- discount_used: ساختار مطابق ci_discount_used (ثبت استفاده کاربر از کد تخفیف)
CREATE TABLE discount_used (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    discount_id BIGINT NOT NULL REFERENCES coupons(id) ON DELETE CASCADE,
    udate INTEGER NOT NULL,             -- زمان استفاده (unix)
    factor_id BIGINT NOT NULL REFERENCES transactions(id) ON DELETE CASCADE
);

CREATE INDEX idx_discount_used_user ON discount_used(user_id);
CREATE INDEX idx_discount_used_discount ON discount_used(discount_id);
CREATE INDEX idx_discount_used_factor ON discount_used(factor_id);

CREATE TABLE subscriptions (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT REFERENCES users(id) ON DELETE CASCADE,
    
    plan_type VARCHAR(50) NOT NULL, -- monthly, yearly, premium
    status VARCHAR(20) DEFAULT 'active', -- active, cancelled, expired
    
    started_at TIMESTAMPTZ DEFAULT NOW(),
    expires_at TIMESTAMPTZ NOT NULL,
    cancelled_at TIMESTAMPTZ,
    
    payment_ref VARCHAR(255),
    auto_renew BOOLEAN DEFAULT FALSE
);

CREATE INDEX idx_subscriptions_user ON subscriptions(user_id, status);
CREATE INDEX idx_subscriptions_expires ON subscriptions(expires_at) WHERE status = 'active';

-- ========================================================
-- 10. Notifications
-- ========================================================

CREATE TABLE notifications (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT REFERENCES users(id) ON DELETE CASCADE,
    
    type VARCHAR(50) NOT NULL, -- order, review, system, promotion
    title VARCHAR(255) NOT NULL,
    body TEXT,
    data JSONB,
    
    is_read BOOLEAN DEFAULT FALSE,
    read_at TIMESTAMPTZ,
    
    created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX idx_notifications_user ON notifications(user_id, is_read, created_at DESC);

-- ========================================================
-- 11. Utility Functions
-- ========================================================

-- Auto-update updated_at timestamp
CREATE OR REPLACE FUNCTION update_updated_at()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- Apply to tables with updated_at
CREATE TRIGGER trg_users_updated_at BEFORE UPDATE ON users FOR EACH ROW EXECUTE FUNCTION update_updated_at();
CREATE TRIGGER trg_products_updated_at BEFORE UPDATE ON products FOR EACH ROW EXECUTE FUNCTION update_updated_at();
CREATE TRIGGER trg_orders_updated_at BEFORE UPDATE ON orders FOR EACH ROW EXECUTE FUNCTION update_updated_at();
CREATE TRIGGER trg_reviews_updated_at BEFORE UPDATE ON reviews FOR EACH ROW EXECUTE FUNCTION update_updated_at();

-- Update product rating stats
CREATE OR REPLACE FUNCTION update_product_rating()
RETURNS TRIGGER AS $$
BEGIN
    UPDATE products SET
        rate_avg = (SELECT COALESCE(AVG(rating), 0) FROM reviews WHERE product_id = COALESCE(NEW.product_id, OLD.product_id) AND is_approved = TRUE),
        rate_count = (SELECT COUNT(*) FROM reviews WHERE product_id = COALESCE(NEW.product_id, OLD.product_id) AND is_approved = TRUE)
    WHERE id = COALESCE(NEW.product_id, OLD.product_id);
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_reviews_rating
AFTER INSERT OR UPDATE OR DELETE ON reviews
FOR EACH ROW EXECUTE FUNCTION update_product_rating();


<?php
/**
 * اجرای migrations برای RBAC
 */

try {
    $pdo = new PDO('pgsql:host=localhost;dbname=madras', 'myuser', 'mypass');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "🚀 اجرای Migrations برای RBAC...\n\n";
    
    // 1. Create roles table
    echo "1️⃣ ایجاد جدول roles...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS roles (
            id BIGSERIAL PRIMARY KEY,
            name VARCHAR(50) UNIQUE NOT NULL,
            display_name VARCHAR(100) NOT NULL,
            description TEXT,
            priority INTEGER DEFAULT 0,
            is_active BOOLEAN DEFAULT true,
            created_at TIMESTAMPTZ DEFAULT NOW(),
            updated_at TIMESTAMPTZ DEFAULT NOW()
        );
        
        CREATE INDEX IF NOT EXISTS idx_roles_name ON roles(name);
        CREATE INDEX IF NOT EXISTS idx_roles_priority ON roles(priority);
        CREATE INDEX IF NOT EXISTS idx_roles_is_active ON roles(is_active);
    ");
    echo "   ✅ جدول roles ایجاد شد\n\n";
    
    // 2. Create permissions table
    echo "2️⃣ ایجاد جدول permissions...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS permissions (
            id BIGSERIAL PRIMARY KEY,
            name VARCHAR(100) UNIQUE NOT NULL,
            display_name VARCHAR(100) NOT NULL,
            category VARCHAR(50) NOT NULL,
            description TEXT,
            is_active BOOLEAN DEFAULT true,
            created_at TIMESTAMPTZ DEFAULT NOW(),
            updated_at TIMESTAMPTZ DEFAULT NOW()
        );
        
        CREATE INDEX IF NOT EXISTS idx_permissions_name ON permissions(name);
        CREATE INDEX IF NOT EXISTS idx_permissions_category ON permissions(category);
        CREATE INDEX IF NOT EXISTS idx_permissions_is_active ON permissions(is_active);
    ");
    echo "   ✅ جدول permissions ایجاد شد\n\n";
    
    // 3. Create role_permissions table
    echo "3️⃣ ایجاد جدول role_permissions...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS role_permissions (
            role_id BIGINT NOT NULL,
            permission_id BIGINT NOT NULL,
            created_at TIMESTAMPTZ DEFAULT NOW(),
            updated_at TIMESTAMPTZ DEFAULT NOW(),
            PRIMARY KEY (role_id, permission_id),
            FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
            FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
        );
        
        CREATE INDEX IF NOT EXISTS idx_role_permissions_role_id ON role_permissions(role_id);
        CREATE INDEX IF NOT EXISTS idx_role_permissions_permission_id ON role_permissions(permission_id);
    ");
    echo "   ✅ جدول role_permissions ایجاد شد\n\n";
    
    // 4. Create user_roles table
    echo "4️⃣ ایجاد جدول user_roles...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_roles (
            user_id BIGINT NOT NULL,
            role_id BIGINT NOT NULL,
            created_at TIMESTAMPTZ DEFAULT NOW(),
            updated_at TIMESTAMPTZ DEFAULT NOW(),
            PRIMARY KEY (user_id, role_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
        );
        
        CREATE INDEX IF NOT EXISTS idx_user_roles_user_id ON user_roles(user_id);
        CREATE INDEX IF NOT EXISTS idx_user_roles_role_id ON user_roles(role_id);
    ");
    echo "   ✅ جدول user_roles ایجاد شد\n\n";
    
    echo "✅ همه migrations با موفقیت اجرا شدند!\n";
    echo "\n🎯 مرحله بعدی: اجرای seeder\n";
    echo "   php seeders/RolesAndPermissionsSeeder.php\n";
    
} catch (PDOException $e) {
    echo "❌ خطا: " . $e->getMessage() . "\n";
    exit(1);
}
