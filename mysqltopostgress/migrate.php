<?php
// تنظیمات اتصال
// -----------------------
$mysqlConfig = [
    'host' => 'localhost',
    'dbname' => 'madras', // نام دیتابیس قدیمی
    'user' => 'root',
    'pass' => 'pass'
];

$pgConfig = [
    'host' => 'localhost',
    'dbname' => 'madras', // نام دیتابیس جدید (پستگرس)
    'user' => 'myuser',
    'pass' => 'mypass'
];


$chunkSize = 1000;

try {
    $mysql = new PDO("mysql:host={$mysqlConfig['host']};dbname={$mysqlConfig['dbname']};charset=utf8mb4", $mysqlConfig['user'], $mysqlConfig['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $pg = new PDO("pgsql:host={$pgConfig['host']};dbname={$pgConfig['dbname']}", $pgConfig['user'], $pgConfig['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    echo "✅ Connected.\n";
    
    // پاکسازی جدول مقصد (اختیاری - اگر می‌خواهید هر بار از صفر شروع شود)
    $pg->exec("TRUNCATE TABLE user_devices RESTART IDENTITY CASCADE");

    // 1. ساخت نقشه کاربران (Old ID -> New ID)
    // چون در جدول ci_user_mobile شناسه قدیمی کاربر (old_id) استفاده شده،
    // اما در جدول user_devices باید شناسه جدید (new_id) را وارد کنیم.
    echo "🗺  Building User Map... ";
    $userMap = [];
    $stmtMap = $pg->query("SELECT old_id, id FROM users WHERE old_id IS NOT NULL");
    while ($row = $stmtMap->fetch(PDO::FETCH_NUM)) {
        $userMap[$row[0]] = $row[1];
    }
    echo "Found " . count($userMap) . " users.\n";

    // 2. انتقال دستگاه‌ها
    echo "📱 Migrating Devices...\n";
    $stmtInsert = $pg->prepare("INSERT INTO user_devices (
        user_id, device_name, os_version, app_version, 
        device_token, device_id, last_active_at, created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

    $totalDevices = $mysql->query("SELECT COUNT(*) FROM ci_user_mobile")->fetchColumn();
    $offset = 0;
    $imported = 0;

    $pg->beginTransaction();

    while ($offset < $totalDevices) {
        $rows = $mysql->query("SELECT * FROM ci_user_mobile LIMIT $chunkSize OFFSET $offset")->fetchAll();
        
        foreach ($rows as $row) {
            $oldUserId = $row['user_id'];

            // فقط اگر کاربر در سیستم جدید وجود دارد، دستگاه را منتقل کن
            if (isset($userMap[$oldUserId])) {
                $newUserId = $userMap[$oldUserId];
                
                // تبدیل تاریخ (معمولاً فرمت int است مثل 1678888888)
                $dateStr = 'now()';
                if (!empty($row['date']) && is_numeric($row['date'])) {
                    $dateStr = date('Y-m-d H:i:s', $row['date']);
                }

                $stmtInsert->execute([
                    $newUserId,
                    $row['mobilemodel'] ?? 'Unknown', // نام مدل گوشی
                    $row['android'] ?? null,          // نسخه اندروید
                    $row['AppVer'] ?? null,           // نسخه اپلیکیشن
                    $row['token'] ?? null,            // توکن پوش
                    $row['mac'] ?? null,              // شناسه دستگاه
                    $dateStr,                         // آخرین فعالیت
                    $dateStr                          // تاریخ ایجاد
                ]);
                $imported++;
            }
        }
        
        $offset += $chunkSize;
        echo "\r   -> Processed: $offset / $totalDevices";
    }

    $pg->commit();
    echo "\n🎉 Done! Imported $imported devices.\n";

} catch (Exception $e) {
    if (isset($pg) && $pg->inTransaction()) $pg->rollBack();
    echo "\n❌ Error: " . $e->getMessage() . "\n";
}
?>