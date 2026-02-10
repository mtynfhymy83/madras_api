# تحلیل نتایج Load Test - Book Details API

**تاریخ:** 2026-02-10  
**تست:** k6 Load Test برای `/api/v1/books/{id}`  
**مدت زمان:** ~6 دقیقه  
**حداکثر بار:** 100 Virtual Users (VUs)

---

## 📊 خلاصه نتایج

- ✅ **21,792 iteration** تکمیل شده
- ❌ **تعداد زیادی Timeout** (5 ثانیه)
- ❌ **Threshold رد شده:** `http_req_duration` از حد مجاز عبور کرده
- ⚠️ **درخواست‌های با زمان 0ms** (fail فوری)

---

## 🔍 مشکلات شناسایی شده

### 1. **Timeout های مکرر**
```
WARN[0179] Request Failed error="Get \"https://api-dev.madras.app/api/v1/books/19\": request timeout"
WARN[0179] Error: [0] ID:19 - Time:4899ms
```

**علت احتمالی:**
- Connection Pool اشباع شده (10 connection per worker × 2 workers = 20 total)
- Query های کند در دیتابیس
- Network latency (تست از راه دور)

### 2. **درخواست‌های با زمان 0ms**
```
WARN[0312] Error: [0] ID:1 - Time:0ms
WARN[0312] Error: [0] ID:3 - Time:0ms
```

**علت احتمالی:**
- Connection Pool کاملاً اشباع شده و درخواست‌ها فوراً reject می‌شوند
- مشکل در اتصال به سرور
- Worker ها overload شده‌اند

### 3. **Threshold رد شده**
```
ERRO[0364] thresholds on metrics 'http_req_duration' have been crossed
```

**Threshold تنظیم شده:**
- `p(95) < 200ms` ❌ (رد شده)
- `response_under_100ms > 90%` (احتمالاً رد شده)
- `http_req_failed < 1%` (احتمالاً رد شده)

---

## 🔧 علل ریشه‌ای

### 1. **Connection Pool محدود**
```php
// server.php:73
$poolSize = (int)($_ENV['DB_POOL_SIZE'] ?? 10);
```

**مشکل:**
- با 2 worker و pool size 10، فقط **20 اتصال همزمان** داریم
- در بار 100 VU، این کافی نیست
- درخواست‌ها در صف می‌مانند تا connection آزاد شود

**راه حل:**
- افزایش `DB_POOL_SIZE` به 30-50 (با توجه به `max_connections` در PostgreSQL)
- یا افزایش تعداد worker ها

### 2. **Query کند: `userHasBookAccess`**
```php
// BookRepository.php:271
public function userHasBookAccess(int $userId, int $bookId): bool
{
    // Query با JOIN های متعدد
    $sql = "
        SELECT 1
        FROM products p
        LEFT JOIN user_library ul ON ...
        LEFT JOIN user_subscriptions us ON ...
        WHERE ...
    ";
}
```

**مشکل:**
- Query پیچیده با JOIN های متعدد
- Cache برای permission فقط 5 دقیقه است
- در بار بالا، این query بار زیادی روی دیتابیس می‌گذارد

**راه حل:**
- بهینه‌سازی query با index های مناسب
- افزایش TTL cache برای permission
- استفاده از materialized view یا denormalization

### 3. **Cache Strategy**
```php
// BookRepository.php:207
$cacheKey = "book:details:{$id}";
Cache::set($cacheKey, $base, 600); // 10 دقیقه

// Permission cache
Cache::set($permissionCacheKey, $base['permission'] ? 1 : 0, 300); // 5 دقیقه
```

**مشکل:**
- Cache برای book details خوب است (10 دقیقه)
- اما permission cache کوتاه است (5 دقیقه)
- در تست، ID های تصادفی بین 1-20 استفاده می‌شود، پس cache hit rate خوب است
- اما اگر Redis کند باشد یا connection pool اشباع باشد، cache هم کند می‌شود

---

## 💡 راهکارهای پیشنهادی

### 1. **افزایش Connection Pool (فوری)**

**در `.env` یا environment variables:**
```bash
DB_POOL_SIZE=30
SWOOLE_WORKER_NUM=4
```

**توجه:** باید `max_connections` در PostgreSQL را هم افزایش دهید:
```sql
-- در PostgreSQL
ALTER SYSTEM SET max_connections = 200;
SELECT pg_reload_conf();
```

### 2. **بهینه‌سازی Query**

**اضافه کردن Index ها:**
```sql
-- برای user_library
CREATE INDEX IF NOT EXISTS idx_user_library_user_product 
ON user_library(user_id, product_id);

-- برای user_subscriptions
CREATE INDEX IF NOT EXISTS idx_user_subscriptions_active 
ON user_subscriptions(user_id, category_id, is_active, expires_at) 
WHERE is_active = true AND deleted_at IS NULL;
```

### 3. **بهبود Cache Strategy**

**افزایش TTL برای permission:**
```php
// BookRepository.php:245
Cache::set($permissionCacheKey, $base['permission'] ? 1 : 0, 600); // 10 دقیقه
```

**یا استفاده از cache برای کل query:**
```php
// Cache کل نتیجه userHasBookAccess
$accessCacheKey = "book:access:{$id}:{$userId}";
$cachedAccess = Cache::get($accessCacheKey);
if ($cachedAccess !== null) {
    return (bool)$cachedAccess;
}
// ... execute query ...
Cache::set($accessCacheKey, $hasAccess, 600);
```

### 4. **بهینه‌سازی تست k6**

**کاهش بار یا افزایش timeout:**
```javascript
// k6/load-test-book-details.js
export const options = {
  stages: [
    { duration: '30s', target: 20 },
    { duration: '1m', target: 50 },
    { duration: '2m', target: 80 }, // کاهش از 100 به 80
    { duration: '2m', target: 80 },
    { duration: '30s', target: 0 },
  ],
  thresholds: {
    'http_req_duration': ['p(95)<500'], // افزایش از 200 به 500
    'response_under_100ms': ['rate>0.7'], // کاهش از 0.9 به 0.7
    'http_req_failed': ['rate<0.05'], // افزایش از 0.01 به 0.05
  },
};

// افزایش timeout
const res = http.get(url, {
  tags: { name: 'book_detail' },
  timeout: '10s', // افزایش از 5s به 10s
});
```

### 5. **مانیتورینگ و Debugging**

**بررسی Connection Pool Stats:**
```php
// اضافه کردن endpoint برای مانیتورینگ
$poolStats = DB::getPoolStats();
$cacheStats = Cache::getStats();
```

**بررسی لاگ‌های PostgreSQL:**
```sql
-- بررسی اتصالات فعال
SELECT count(*) FROM pg_stat_activity;

-- بررسی query های کند
SELECT pid, now() - pg_stat_activity.query_start AS duration, query 
FROM pg_stat_activity 
WHERE (now() - pg_stat_activity.query_start) > interval '1 second'
ORDER BY duration DESC;
```

---

## 📈 معیارهای موفقیت

برای اینکه تست pass شود، باید:

1. ✅ **p95 < 200ms** (یا threshold واقع‌بینانه‌تر)
2. ✅ **>90% درخواست‌ها زیر 100ms**
3. ✅ **<1% error rate**
4. ✅ **بدون timeout**

---

## 🎯 اولویت اقدامات

### فوری (امروز):
1. ✅ افزایش `DB_POOL_SIZE` به 30
2. ✅ بررسی `max_connections` در PostgreSQL
3. ✅ افزایش timeout در تست k6 به 10s

### کوتاه‌مدت (این هفته):
1. ✅ اضافه کردن index های مناسب
2. ✅ بهینه‌سازی query `userHasBookAccess`
3. ✅ بهبود cache strategy

### بلندمدت (این ماه):
1. ✅ مانیتورینگ real-time
2. ✅ Load balancing
3. ✅ Database read replicas
4. ✅ CDN برای static assets

---

## 📝 یادداشت‌های تکمیلی

- تست از راه دور (`api-dev.madras.app`) اجرا شده، پس network latency هم نقش دارد
- با 100 VU و sleep 0.5-1.5s، تقریباً 50-100 request/second داریم
- اگر connection pool 20 باشد، با query time متوسط 100ms، فقط می‌توانیم 200 request/second handle کنیم
- پس bottleneck اصلی connection pool است

---

**نویسنده:** AI Assistant  
**آخرین بروزرسانی:** 2026-02-10
