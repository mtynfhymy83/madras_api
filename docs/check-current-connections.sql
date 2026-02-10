-- بررسی تعداد اتصالات فعلی
SELECT 
    count(*) as current_connections,
    (SELECT setting::int FROM pg_settings WHERE name = 'max_connections') as max_connections,
    (SELECT setting::int FROM pg_settings WHERE name = 'max_connections') - count(*) as available_connections
FROM pg_stat_activity
WHERE datname = current_database();

-- لیست اتصالات فعلی (برای debug)
SELECT 
    pid,
    usename,
    application_name,
    client_addr,
    state,
    query_start,
    state_change,
    NOW() - state_change as idle_duration
FROM pg_stat_activity
WHERE datname = current_database()
ORDER BY state_change DESC;
