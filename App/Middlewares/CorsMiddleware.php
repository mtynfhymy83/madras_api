<?php
namespace App\Middlewares;

use App\Core\MiddlewareInterface;
use Swoole\Http\Request;
use Swoole\Http\Response;

class CorsMiddleware implements MiddlewareInterface
{
    /**
     * لیست origins مجاز (برای امنیت بیشتر)
     */
    private array $allowedOrigins = [
        'http://localhost',
        'http://localhost:9501',
        'http://127.0.0.1',
        'http://127.0.0.1:9501',
    ];

    public function handle(Request $request, Response $response, callable $next)
    {
        // Get origin from request headers
        $headers = $request->header ?? [];
        $origin = $headers['origin'] ?? $headers['Origin'] ?? null;
        
        // اگر origin وجود داشت و در لیست مجاز بود، از آن استفاده کن
        // در غیر این صورت از * استفاده کن (برای development)
        $allowedOrigin = '*';
        if ($origin && $this->isOriginAllowed($origin)) {
            $allowedOrigin = $origin;
        }
        
        // Set CORS headers
        $response->header('Access-Control-Allow-Origin', $allowedOrigin);
        $response->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
        $response->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, Origin');
        $response->header('Access-Control-Allow-Credentials', 'true');
        $response->header('Access-Control-Max-Age', '86400');
        $response->header('Access-Control-Expose-Headers', 'Content-Length, Content-Type');

        // مدیریت درخواست‌های Preflight (OPTIONS)
        $method = $request->server['request_method'] ?? 'GET';
        if ($method === 'OPTIONS') {
            $response->status(204);
            $response->end('');
            return;
        }

        return $next($request, $response);
    }

    /**
     * بررسی اینکه آیا origin مجاز است یا نه
     */
    private function isOriginAllowed(string $origin): bool
    {
        // در development، همه origins را مجاز می‌کنیم
        // در production، باید این لیست را محدود کنید
        return true; // برای development
        
        // برای production:
        // foreach ($this->allowedOrigins as $allowed) {
        //     if (str_starts_with($origin, $allowed)) {
        //         return true;
        //     }
        // }
        // return false;
    }
}