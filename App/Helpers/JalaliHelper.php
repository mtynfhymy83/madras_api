<?php

namespace App\Helpers;

/**
 * تبدیل تاریخ میلادی به شمسی (جلالی) برای نمایش در API
 */
class JalaliHelper
{
    private static bool $jdfLoaded = false;

    /**
     * بارگذاری یکباره jdf.php
     */
    private static function loadJdf(): void
    {
        if (self::$jdfLoaded) {
            return;
        }
        $path = __DIR__ . '/jdf.php';
        if (file_exists($path)) {
            require_once $path;
            self::$jdfLoaded = true;
        }
    }

    /**
     * تبدیل رشته یا timestamp تاریخ میلادی به شمسی
     *
     * @param string|int|null $datetime تاریخ میلادی (مثلاً 2024-01-15 10:30:00) یا timestamp
     * @param string $format فرمت خروجی شمسی (پیش‌فرض: Y/m/d H:i)
     * @param string $timezone منطقه زمانی (پیش‌فرض: Asia/Tehran)
     * @return string|null تاریخ شمسی یا null در صورت ورودی نامعتبر
     */
    public static function toJalali($datetime, string $format = 'Y/m/d H:i', string $timezone = 'Asia/Tehran'): ?string
    {
        if ($datetime === null || $datetime === '') {
            return null;
        }

        self::loadJdf();

        $ts = is_numeric($datetime)
            ? (int) $datetime
            : strtotime($datetime);

        if ($ts === false) {
            return null;
        }

        if (!function_exists('jdate')) {
            return date('Y-m-d H:i', $ts);
        }

        return jdate($format, $ts, '', $timezone, 'en');
    }
}
