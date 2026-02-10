# عیب‌یابی: Database Pool Exhausted در Production

## مشکل: "too many clients already" یا "All 5 connections are in use"

این یعنی تعداد اتصالات از `max_connections` در PostgreSQL بیشتر شده است.

## بررسی‌های لازم

### 1. بررسی تعداد اتصالات فعلی در PostgreSQL

```sql
-- در production PostgreSQL
SELECT 
    count(*) as current_connections,
    (SELECT setting::int FROM pg_settings WHERE name = 'max_connections') as max_connections
FROM pg_stat_activity
WHERE datname = current_database();
```

یا از فایل آماده استفاده کن:
```bash
psql -U postgres -d madras -f docs/check-db-connections.sql
```

### 2. بررسی تنظیمات در Production

```bash
# بررسی environment variables
echo $DB_POOL_SIZE
echo $SWOOLE_WORKER_NUM

# یا اگر از Docker استفاده می‌کنی:
docker exec php_swoole_app_new env | grep DB_POOL_SIZE
docker exec php_swoole_app_new env | grep SWOOLE_WORKER_NUM
```

### 3. بررسی تعداد Worker های در حال اجرا

```bash
# اگر از Docker استفاده می‌کنی:
docker exec php_swoole_app_new ps aux | grep "php.*server.php"

# یا بررسی لاگ:
docker logs php_swoole_app_new 2>&1 | grep "Worker.*starting"
```

### 4. بررسی چند Instance

```bash
# آیا چند instance از سرور در حال اجراست؟
ps aux | grep "php.*server.php" | wc -l

# یا اگر از Docker:
docker ps | grep php_swoole | wc -l
```

## راه‌حل‌ها

### راه‌حل 1: افزایش max_connections در PostgreSQL

```sql
-- در postgresql.conf
max_connections = 200

-- سپس PostgreSQL را ریستارت کن
```

یا در Docker:
```yaml
command: postgres -c max_connections=200 -c shared_buffers=256MB
```

### راه‌حل 2: کاهش Pool Size (اگر نمی‌توانی max_connections را افزایش بدهی)

```env
# در .env یا environment variables
DB_POOL_SIZE=15
SWOOLE_WORKER_NUM=4
# کل: 4 × 15 = 60 اتصال (امن با max_connections=100)
```

### راه‌حل 3: بررسی چند Instance

اگر چند instance از سرور در حال اجراست، باید یکی را متوقف کنی:

```bash
# پیدا کردن همه process ها
ps aux | grep "php.*server.php"

# متوقف کردن instance های اضافی
kill [PID]
```

### راه‌حل 4: بررسی اتصالات باز مانده

```sql
-- بستن اتصالات idle قدیمی
SELECT pg_terminate_backend(pid)
FROM pg_stat_activity
WHERE datname = current_database()
  AND state = 'idle'
  AND state_change < NOW() - INTERVAL '10 minutes';
```

## فرمول محاسبه

```
کل اتصالات = تعداد Worker ها × DB_POOL_SIZE × تعداد Instance ها
```

مثال:
- 4 worker × 30 pool size × 1 instance = 120 اتصال
- 4 worker × 30 pool size × 2 instance = 240 اتصال ❌ (اگر max_connections=200 باشد)

## بعد از تغییرات

1. سرور را ریستارت کن
2. بررسی کن که worker ها بدون خطا شروع می‌شوند
3. بررسی کن که تعداد اتصالات کمتر از max_connections است
