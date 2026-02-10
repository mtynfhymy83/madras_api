<?php

namespace App\Helpers;

use Swoole\Coroutine;

class Context
{
    /**
     * ذخیره داده در کانتکست جاری
     */
    public static function set(string $key, $value): void
    {
        $ctx = Coroutine::getContext();
        if ($ctx) {
            $ctx[$key] = $value;
        }
    }

    /**
     * دریافت داده از کانتکست جاری
     */
    public static function get(string $key)
    {
        $ctx = Coroutine::getContext();
        if ($ctx && isset($ctx[$key])) {
            return $ctx[$key];
        }
        return null;
    }

    /**
     * دریافت آبجکت Request فعلی
     */
    public static function getRequest(): ?\Swoole\Http\Request
    {
        return self::get('request');
    }

    /**
     * دریافت آبجکت Response فعلی
     */
    public static function getResponse(): ?\Swoole\Http\Response
    {
        return self::get('response');
    }
}