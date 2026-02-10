# راهنمای تنظیمات عملکرد برای ترافیک بالا

## مشکل: Database Pool Exhausted

اگر در تست فشار خطای `Database pool exhausted! All connections are in use` می‌بینی، یعنی تعداد اتصالات دیتابیس کافی نیست.

## تنظیمات پیشنهادی

### ⚠️ محدودیت مهم: max_connections در PostgreSQL

PostgreSQL به صورت پیش‌فرض `max_connections = 100` دارد. **کل اتصالات باید کمتر از این مقدار باشد** (چون PostgreSQL خودش هم اتصالاتی برای maintenance دارد).

### برای ترافیک متوسط (50-100 کاربر همزمان)

```env
DB_POOL_SIZE=15
SWOOLE_WORKER_NUM=4
```

**کل اتصالات:** 4 worker × 15 = 60 اتصال (امن)

### برای ترافیک بالا (100-200 کاربر همزمان)

```env
DB_POOL_SIZE=20
SWOOLE_WORKER_NUM=4
```

**کل اتصالات:** 4 worker × 20 = 80 اتصال (امن)

### برای ترافیک بسیار بالا (200+ کاربر همزمان)

**گزینه 1:** افزایش max_connections در PostgreSQL (توصیه می‌شود)

```sql
-- در postgresql.conf
max_connections = 200
```

سپس:

```env
DB_POOL_SIZE=30
SWOOLE_WORKER_NUM=4
```

**کل اتصالات:** 4 worker × 30 = 120 اتصال

**گزینه 2:** بدون تغییر PostgreSQL (محدودتر)

```env
DB_POOL_SIZE=15
SWOOLE_WORKER_NUM=6
```

**کل اتصالات:** 6 worker × 15 = 90 اتصال (امن)

### تنظیمات فعلی (پیشنهادی برای تست فشار)

**اگر از Docker استفاده می‌کنی** (max_connections=200 در docker-compose.yml):

```env
DB_POOL_SIZE=30
SWOOLE_WORKER_NUM=4
```

**کل اتصالات:** 4 worker × 30 = 120 اتصال (امن با max_connections=200)

**اگر از PostgreSQL مستقیم استفاده می‌کنی** (max_connections=100 پیش‌فرض):

```env
DB_POOL_SIZE=15
SWOOLE_WORKER_NUM=4
```

**کل اتصالات:** 4 worker × 15 = 60 اتصال (امن با max_connections=100)

## نکات مهم

1. **هر worker یک pool جداگانه دارد** — پس کل اتصالات = `SWOOLE_WORKER_NUM × DB_POOL_SIZE`

2. **محدودیت PostgreSQL:** PostgreSQL به‌صورت پیش‌فرض حداکثر 100 اتصال همزمان را می‌پذیرد. برای افزایش:

   **در Docker:**
   ```bash
   # در docker-compose.yml یا Dockerfile
   command: postgres -c max_connections=200
   ```
   
   **یا در postgresql.conf:**
   ```conf
   max_connections = 200
   ```
   
   سپس PostgreSQL را ریستارت کن.

3. **محدودیت حافظه:** هر اتصال PostgreSQL حدود 10MB RAM مصرف می‌کند. پس 200 اتصال ≈ 2GB RAM فقط برای دیتابیس.

4. **بهینه‌سازی کوئری:** قبل از افزایش pool size، مطمئن شو کوئری‌ها بهینه هستند و از کش Redis استفاده می‌کنند.

5. **مشکل کوئری‌های کند:** اگر بعد از افزایش pool size هنوز خطا می‌بینی، ممکن است مشکل از کوئری‌های کند باشد که اتصالات را برای مدت طولانی نگه می‌دارند. بررسی کن:
   - آیا `userHasBookAccess` با JOIN‌های زیاد کند است؟
   - از `EXPLAIN ANALYZE` در PostgreSQL استفاده کن تا کوئری‌های کند را پیدا کنی.

## بررسی وضعیت Pool

برای دیدن آمار pool در runtime:

```php
// در یک Controller یا endpoint مانیتورینگ
$stats = \App\Database\DB::getPoolStats();
// returns: ['available' => X, 'capacity' => Y, 'in_use' => Z] یا null اگر pool init نشده
```

## تست

بعد از تغییر تنظیمات، دوباره تست فشار را اجرا کن:

```bash
k6 run k6/load-test-book-details.js
```

اگر هنوز خطا می‌بینی، `DB_POOL_SIZE` را بیشتر کن یا تعداد workerها را افزایش بده.
