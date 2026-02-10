-- بستن اتصالات idle قدیمی (بیش از 5 دقیقه)
-- اجرا: psql -U postgres -d madras -f docs/close-idle-connections.sql

-- ابتدا ببین چه اتصالاتی داریم
SELECT 
    pid,
    usename,
    application_name,
    client_addr,
    state,
    state_change,
    NOW() - state_change as idle_duration
FROM pg_stat_activity
WHERE datname = current_database()
  AND state = 'idle'
ORDER BY state_change ASC;

-- بستن اتصالات idle قدیمی (بیش از 5 دقیقه)
SELECT 
    pg_terminate_backend(pid) as terminated,
    pid,
    usename,
    state_change
FROM pg_stat_activity
WHERE datname = current_database()
  AND state = 'idle'
  AND state_change < NOW() - INTERVAL '5 minutes';

-- بررسی تعداد اتصالات بعد از بستن
SELECT 
    count(*) as current_connections,
    (SELECT setting::int FROM pg_settings WHERE name = 'max_connections') as max_connections,
    (SELECT setting::int FROM pg_settings WHERE name = 'max_connections') - count(*) as available_connections
FROM pg_stat_activity
WHERE datname = current_database();
