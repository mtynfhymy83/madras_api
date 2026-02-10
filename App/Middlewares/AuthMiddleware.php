<?php
namespace App\Middlewares;

use App\Core\MiddlewareInterface; // اینترفیسی که ساختیم
use App\Auth\JWTAuth;
use App\Traits\ResponseTrait;
use Swoole\Http\Request;
use Swoole\Http\Response;

class AuthMiddleware implements MiddlewareInterface
{
    use JWTAuth;
    use ResponseTrait;

    /**
     * لیست مسیرهایی که نیاز به توکن ندارند
     */
    private array $publicPaths = [
        '/api/v1/auth/login',
        '/api/v1/auth/register',
        '/api/v1/auth/eitaa',
        '/v1/auth/login',
        '/v1/auth/register',
        '/v1/auth/eitaa',
        '/api/v1/swagger.html',
        '/api/v1/docs/swagger.yaml',
        '/api/v1/base64-converter',
        '/api/v1/base64_converter.html',
        '/api/v1/diagnostics',
        '/api/v1/logs',
        '/api/v1/benchmark',
        '/v1/swagger.html',
        '/v1/docs/swagger.yaml',
        '/v1/base64-converter',
        '/v1/base64_converter.html',
        '/v1/diagnostics',
        '/v1/logs',
        '/v1/benchmark',
        '/base64_converter.html',
        '/swagger.html',
        '/docs/swagger.yaml',
        '/diagnostics',
        '/logs',
        '/benchmark',
    ];

    /**
     * الگوهای regex برای مسیرهای عمومی (همه HTTP methods)
     */
    private array $publicPatterns = [
        '#^/api/v1/books$#',              // لیست کتاب‌ها
        '#^/api/v1/books/\d+$#',          // جزئیات یک کتاب
        '#^/v1/books$#',
        '#^/v1/books/\d+$#',
        '#^/api/v1/payment/paybook/\d+$#',   // صفحه/اطلاعات پرداخت کتاب
        '#^/api/v1/payment/verify/[a-z]+$#', // callback تأیید پرداخت بانک
        '#^/v1/payment/paybook/\d+$#',
        '#^/v1/payment/verify/[a-z]+$#',
        '#^/api/v1/benchmark/#',          // Benchmark endpoints
        '#^/v1/benchmark/#',
    ];

    /**
     * الگوهای regex برای مسیرهای عمومی فقط برای GET
     */
    private array $publicGetOnlyPatterns = [
        '#^/api/v1/books/\d+/reviews$#',  // لیست دیدگاه‌ها (GET only)
        '#^/v1/books/\d+/reviews$#',
    ];

    public function handle(Request $request, Response $response, callable $next)
    {
        // 1. دریافت URI تمیز شده (بدون Query String)
        $uri = explode('?', $request->server['request_uri'])[0];
        $method = $request->server['request_method'] ?? 'GET';

        // 2. اگر مسیر عمومی است، برو بعدی (نیازی به توکن نیست)
        if ($this->isPublicPath($uri, $method)) {
            return $next($request, $response);
        }

        // 3. استخراج توکن
        $token = $this->getTokenFromRequest($request);

        if (!$token) {
            return $this->unauthorizedResponse($response, 'توکن احراز هویت یافت نشد');
        }

        // 4. اعتبارسنجی توکن (از تریت JWTAuth)
        // فرض بر این است که verifyToken اگر معتبر نباشد false برمی‌گرداند
        $isValid = $this->verifyToken($token);

        if (!$isValid) {
            return $this->unauthorizedResponse($response, 'توکن نامعتبر یا منقضی شده است');
        }

        // 5. (اختیاری) اگر توکن معتبر بود، اطلاعات کاربر را به ریکوئست اضافه کن
        // تا در کنترلر دوباره نیاز به دیکد کردن نباشد
        // $request->user_id = $this->getUserIdFromToken($token);

        // 6. موفقیت: ادامه زنجیره
        return $next($request, $response);
    }

    /**
     * بررسی اینکه آیا مسیر جاری جزو مسیرهای عمومی است یا خیر
     */
    private function isPublicPath(string $uri, string $method = 'GET'): bool
    {
        // 1. چک مسیرهای ثابت
        foreach ($this->publicPaths as $path) {
            if ($uri === $path || str_starts_with($uri, $path)) {
                return true;
            }
        }

        // 2. چک الگوهای regex (برای مسیرهای داینامیک مثل /books/{id})
        foreach ($this->publicPatterns as $pattern) {
            if (preg_match($pattern, $uri)) {
                return true;
            }
        }

        // 3. چک الگوهای GET-only (فقط برای متد GET)
        if ($method === 'GET') {
            foreach ($this->publicGetOnlyPatterns as $pattern) {
                if (preg_match($pattern, $uri)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * استخراج استاندارد توکن از هدر Authorization
     */
    private function getTokenFromRequest(Request $request): ?string
    {
        $headers = $request->header ?? [];

        // استاندارد: Authorization: Bearer <token>
        $authHeader = $headers['authorization'] ?? $headers['Authorization'] ?? null;

        if ($authHeader && preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return $matches[1];
        }

        // پشتیبانی از هدرهای غیر استاندارد (اختیاری)
        return $headers['token'] ?? null;
    }

    /**
     * ارسال پاسخ خطا و قطع زنجیره
     */
    private function unauthorizedResponse(Response $response, string $message)
    {
        $response->status(401);
        $response->header('Content-Type', 'application/json; charset=utf-8');


        $response->end(json_encode([
            'success' => false,
            'status' => 401,
            'message' => $message,
            'data' => null
        ], JSON_UNESCAPED_UNICODE));


    }
}