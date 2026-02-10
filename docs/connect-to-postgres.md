# راهنمای اتصال به PostgreSQL

## اگر از Docker استفاده می‌کنی:

### روش 1: از Host Machine (خارج از Docker)
```bash
# اگر PostgreSQL port 5432 را expose کرده باشد
psql -h localhost -p 5432 -U myuser -d madras

# یا اگر از IP استفاده می‌کنی
psql -h 10.0.1.155 -p 5432 -U myuser -d madras
```

### روش 2: از داخل Container PostgreSQL
```bash
# وارد شدن به container PostgreSQL
docker exec -it postgres_db bash

# سپس اجرای psql
psql -U myuser -d madras
```

### روش 3: اجرای مستقیم psql از Docker
```bash
# بدون وارد شدن به container
docker exec -it postgres_db psql -U myuser -d madras
```

## اگر PostgreSQL مستقیماً روی سرور است (بدون Docker):

```bash
# اتصال مستقیم
psql -h 10.0.1.155 -p 5432 -U myuser -d madras

# یا اگر PostgreSQL روی همان سرور است
psql -h localhost -p 5432 -U myuser -d madras
```

## بعد از اتصال، دستورات SQL را اجرا کن:

```sql
-- بررسی max_connections
SHOW max_connections;

-- بررسی تعداد اتصالات فعلی
SELECT 
    count(*) as current_connections,
    (SELECT setting::int FROM pg_settings WHERE name = 'max_connections') as max_connections
FROM pg_stat_activity
WHERE datname = current_database();

-- بستن اتصالات idle قدیمی
SELECT pg_terminate_backend(pid)
FROM pg_stat_activity
WHERE datname = current_database()
  AND state = 'idle'
  AND state_change < NOW() - INTERVAL '5 minutes';
```
