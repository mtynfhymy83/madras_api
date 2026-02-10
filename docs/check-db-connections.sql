-- بررسی تعداد اتصالات فعلی و max_connections در PostgreSQL
-- اجرا در production: psql -U postgres -d madras -f docs/check-db-connections.sql

-- 1) بررسی max_connections
SHOW max_connections;

-- 2) تعداد اتصالات فعلی
SELECT 
    count(*) as current_connections,
    (SELECT setting::int FROM pg_settings WHERE name = 'max_connections') as max_connections,
    (SELECT setting::int FROM pg_settings WHERE name = 'max_connections') - count(*) as available_connections
FROM pg_stat_activity
WHERE datname = current_database();

-- 3) لیست اتصالات فعلی (برای debug)
SELECT 
    pid,
    usename,
    application_name,
    client_addr,
    state,
    query_start,
    state_change,
    wait_event_type,
    wait_event
FROM pg_stat_activity
WHERE datname = current_database()
ORDER BY query_start DESC;

-- 4) اگر می‌خواهی اتصالات idle را ببینی
SELECT 
    count(*) as idle_connections
FROM pg_stat_activity
WHERE datname = current_database() 
  AND state = 'idle'
  AND state_change < NOW() - INTERVAL '5 minutes';
