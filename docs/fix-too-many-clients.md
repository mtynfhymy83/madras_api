# راه‌حل فوری: "too many clients already"

## مشکل
PostgreSQL نمی‌تواند اتصال جدید بدهد چون `max_connections` پر شده است.

## راه‌حل فوری (برای راه‌اندازی سرور)

### 1. کاهش موقت Pool Size
در `.env` تغییر دادم:
```env
DB_POOL_SIZE=10
SWOOLE_WORKER_NUM=2
# کل: 2 × 10 = 20 اتصال
```

### 2. بستن اتصالات باز مانده در PostgreSQL

```sql
-- اتصالات idle قدیمی را ببند
SELECT pg_terminate_backend(pid)
FROM pg_stat_activity
WHERE datname = current_database()
  AND state = 'idle'
  AND state_change < NOW() - INTERVAL '5 minutes';

-- یا همه اتصالات idle را ببند (احتیاط!)
SELECT pg_terminate_backend(pid)
FROM pg_stat_activity
WHERE datname = current_database()
  AND state = 'idle';
```

### 3. بررسی چند Instance

```bash
# اگر از Docker استفاده می‌کنی:
docker ps | grep php_swoole

# اگر مستقیم اجرا می‌کنی:
ps aux | grep "php.*server.php"
```

اگر چند instance داری، یکی را متوقف کن:
```bash
docker stop [container_id]
# یا
kill [PID]
```

### 4. بررسی max_connections در PostgreSQL

```sql
SHOW max_connections;
```

اگر خیلی کم است (مثلاً 100)، افزایش بده:
```sql
ALTER SYSTEM SET max_connections = 200;
-- سپس PostgreSQL را ریستارت کن
```

## بعد از حل مشکل

بعد از اینکه اتصالات باز مانده را بستی و مطمئن شدی که فقط یک instance در حال اجراست، می‌توانی pool size را دوباره افزایش بدهی:

```env
DB_POOL_SIZE=30
SWOOLE_WORKER_NUM=4
```

اما مطمئن شو که:
- `max_connections` در PostgreSQL حداقل 150-200 باشد
- فقط یک instance از سرور در حال اجرا باشد
- اتصالات idle به صورت منظم بسته می‌شوند
