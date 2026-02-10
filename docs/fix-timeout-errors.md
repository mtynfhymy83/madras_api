# راه‌حل: Timeout Errors در Load Test

## مشکل
- بعضی درخواست‌ها timeout می‌خورند (5 ثانیه)
- P95 = 312ms (باید < 200ms)
- فقط 86.7% درخواست‌ها زیر 100ms هستند (باید > 90%)

## تغییرات انجام شده

### 1. افزایش Redis Timeout
- Connection timeout: 2s → 5s
- Read timeout: 3s اضافه شد
- برای Redis remote (`89.42.136.85`) این ضروری است

### 2. Cache کردن Permission
- Permission حالا جداگانه cache می‌شود
- Cache key: `book:permission:{id}:{userId}`
- TTL: 5 دقیقه
- این باعث می‌شود query `userHasBookAccess` کمتر اجرا شود

## کارهای باقی‌مانده

### 1. ایجاد Indexes در PostgreSQL

```bash
# اجرای اسکریپت ایجاد indexes
psql -h 10.0.1.155 -p 5432 -U myuser -d madras -f docs/optimize-book-access-indexes.sql
```

این indexes باعث می‌شود:
- `userHasBookAccess` query سریع‌تر شود
- JOIN ها بهینه‌تر اجرا شوند

### 2. بررسی Slow Queries

```sql
-- فعال کردن log slow queries در PostgreSQL
ALTER SYSTEM SET log_min_duration_statement = 100; -- log queries > 100ms
SELECT pg_reload_conf();

-- سپس بررسی لاگ‌ها
-- بعد از تست، دوباره غیرفعال کن:
ALTER SYSTEM SET log_min_duration_statement = -1;
SELECT pg_reload_conf();
```

### 3. بررسی Redis Performance

```bash
# بررسی latency Redis
redis-cli -h 89.42.136.85 -p 56474 --latency

# اگر latency بالا است (> 10ms)، باید:
# - Redis را به سرور نزدیک‌تر منتقل کنی
# - یا از Redis local استفاده کنی
```

### 4. بررسی Database Pool

```bash
# بررسی pool stats (اگر endpoint monitoring داری)
# یا از لاگ‌های سرور استفاده کن
```

## تست دوباره

بعد از اعمال تغییرات:

```bash
# اجرای تست k6
cd k6
k6 run load-test-book-details.js
```

## انتظارات

بعد از بهینه‌سازی:
- P95 باید < 200ms شود
- > 90% درخواست‌ها زیر 100ms باشند
- Timeout errors باید کاهش یابد

## اگر هنوز مشکل داری

1. **بررسی Network Latency**: 
   - اگر Redis remote است، latency می‌تواند مشکل ایجاد کند
   - راه‌حل: استفاده از Redis local یا نزدیک‌تر

2. **بررسی Database Load**:
   - اگر database overload است، باید `max_connections` را افزایش بدهی
   - یا pool size را کاهش بدهی

3. **بررسی Slow Queries**:
   - از `EXPLAIN ANALYZE` استفاده کن
   - Query های کند را بهینه کن
