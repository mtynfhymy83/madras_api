<?php
declare(strict_types=1);

// Disable proxy env before autoload to avoid Swoole curl handler issues
putenv('HTTP_PROXY=');
putenv('HTTPS_PROXY=');
putenv('http_proxy=');
putenv('https_proxy=');
putenv('NO_PROXY=*');
putenv('no_proxy=*');

require_once __DIR__ . '/vendor/autoload.php';

use App\Database\PDOPool;
use App\Database\DB;
use App\Cache\Cache;
use App\Routers\Router;
use App\Core\Pipeline;
use App\Middlewares\AuthMiddleware;
use App\Middlewares\CorsMiddleware;
use Dotenv\Dotenv;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Http\Server;

// Load .env file if present
if (file_exists(__DIR__ . '/.env')) {
    Dotenv::createImmutable(__DIR__)->load();
}

// Sync getenv() into $_ENV (in Docker/CLI, container env vars often only appear in getenv)
$envVars = [
    'DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USERNAME', 'DB_PASSWORD',
    'APP_HOST', 'APP_PORT', 'APP_DEBUG', 'APP_URL', 'BASE_URL',
    'DB_POOL_SIZE', 'JWT_SECRET', 'JWT_ALGO', 'JWT_TTL', 'STORAGE_DRIVER', 'EITAA_BOT_TOKEN',
    'SAMAN_ID', 'SAMAN_TERMINAL', 'SAMAN_USERNAME', 'SAMAN_PASSWORD', 'PAYMENT_ONLINE',
];
foreach ($envVars as $k) {
    if (!array_key_exists($k, $_ENV) && ($v = getenv($k)) !== false) {
        $_ENV[$k] = $v;
    }
}

$appDebug = ($_ENV['APP_DEBUG'] ?? '0') === '1';

$server = new Server($_ENV['APP_HOST'] ?? '0.0.0.0', (int)($_ENV['APP_PORT'] ?? 9501));

$server->set([
    'worker_num' => (int)($_ENV['SWOOLE_WORKER_NUM'] ?? 2), // Reduced from 4 to 2 for lower resource usage
    'enable_coroutine' => true,
    // Disable curl hook to avoid Swoole curl handler crash
    'hook_flags' => SWOOLE_HOOK_ALL & ~SWOOLE_HOOK_CURL,
    'log_file' => __DIR__ . '/storage/logs/swoole.log',
    'package_max_length' => 100 * 1024 * 1024, // 100MB
    'socket_buffer_size' => 100 * 1024 * 1024, // 100MB
    'buffer_output_size' => 100 * 1024 * 1024, // 100MB
    'max_request' => 10000, // Worker restart after 10k requests to prevent memory leaks
    'max_wait_time' => 60, // Max wait time for worker shutdown
    'reload_async' => true, // Enable async worker reload
]);

// وضعیت برای نگهداری اشیاء Singleton در هر ورکر
$state = [
    'router' => null,
    'middlewares' => []
];

$server->on('WorkerStart', function ($server, $workerId) use (&$state) {
    try {
        // 1. راه اندازی دیتابیس (Pool)
        // Pool size per worker (4 workers × 30 = 120 total connections با Docker)
        // توجه: باید کمتر از max_connections در PostgreSQL باشد
        $poolSize = (int)($_ENV['DB_POOL_SIZE'] ?? 10);
        $workerNum = (int)($_ENV['SWOOLE_WORKER_NUM'] ?? 2);
        error_log("🔧 Worker #$workerId starting: DB_POOL_SIZE=$poolSize, SWOOLE_WORKER_NUM=$workerNum");
        
        $pool = new PDOPool($poolSize);
        DB::init($pool);
    } catch (\Throwable $e) {
        error_log("❌ CRITICAL: Worker #$workerId failed to initialize: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        // Don't crash - let worker continue but DB operations will fail gracefully
        return;
    }

    // 2. راه اندازی Cache (Redis)
    Cache::init();

    // 3. راه اندازی روتر
    $router = new Router();
    $routesCallback = require __DIR__ . '/App/Routers/routes.php';
    if (is_callable($routesCallback)) {
        $routesCallback($router);
    }
    $state['router'] = $router;

    // 4. راه اندازی میدلورها (ترتیب مهم است)
    $state['middlewares'] = [
        new CorsMiddleware(), // ✅ وظیفه CORS و OPTIONS handling با ایشان است
        new AuthMiddleware(), // ✅ وظیفه امنیت با ایشان است
    ];

    if ($workerId === 0) {
        echo "🚀 Server started at http://{$server->host}:{$server->port}\n";
    }
});

// ---------------- REQUEST HANDLER ----------------
$server->on('Request', function (Request $request, Response $response) use (&$state, $appDebug) {

    $uri = $request->server['request_uri'] ?? '/';
    $method = $request->server['request_method'] ?? 'GET';

    // Simple healthcheck (before middleware)
    $pathOnly = explode('?', $uri, 2)[0];
    
    if ($pathOnly === '/health' || $pathOnly === '/ping') {
        $response->header('Content-Type', 'application/json');
        $response->end(json_encode([
            'status' => 'ok',
            'timestamp' => date('Y-m-d H:i:s'),
            'php_version' => PHP_VERSION
        ]));
        return;
    }

    // ❌ کدهای دستی CORS حذف شد (چون میدلور داریم)

    // اجرای Pipeline
    try {
        (new Pipeline())
            ->through($state['middlewares'])
            ->then($request, $response, function (Request $req, Response $res) use (&$state, $appDebug) {

                // اینجا فقط زمانی اجرا میشه که از سدِ میدلورها (CORS و Auth) رد شده باشیم
                try {
                    if (!$state['router']) {
                        $router = new Router();
                        $routesCallback = require __DIR__ . '/App/Routers/routes.php';
                        if (is_callable($routesCallback)) {
                            $routesCallback($router);
                        }
                        $state['router'] = $router;
                    }

                    /** @var Router $router */
                    $router = $state['router'];

                    // پردازش URL
                    $uri = $req->server['request_uri'] ?? '/';
                    $pathOnly = explode('?', $uri, 2)[0];
                    if (str_contains($pathOnly, '%')) $pathOnly = rawurldecode($pathOnly);

                    $version = extractApiVersion($pathOnly);
                    $cleanPath = extractPath($pathOnly);
                    $method = $req->server['request_method'] ?? 'GET';

                    // مسیریابی
                    $result = $router->resolve($version, $method, $cleanPath, $req);

                    // ارسال پاسخ
                    sendResponse($res, $result, $req);

                } catch (\Throwable $e) {
                    handleException($res, $e, $appDebug, $req);
                }
            });
    } catch (\Throwable $e) {
        // Critical error in middleware/pipeline
        error_log("CRITICAL ERROR: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
        if (!$response->isWritable()) {
            return;
        }
        $response->header('Content-Type', 'application/json');
        $response->status(500);
        $response->end(json_encode([
            'success' => false,
            'message' => 'Internal server error',
            'error' => $appDebug ? $e->getMessage() : 'Server error',
            'trace' => $appDebug ? $e->getTraceAsString() : null
        ]));
    }
});

$server->start();

// ---------------- HELPER FUNCTIONS ----------------

/**
 * تنظیم CORS headers برای پاسخ. همه دامنه‌ها مجازند (Origin درخواست برگردانده می‌شود).
 */
function setCorsHeaders(Response $response, Request $request): void
{
    $headers = $request->header ?? [];
    $origin = $headers['origin'] ?? $headers['Origin'] ?? '*';

    $response->header('Access-Control-Allow-Origin', $origin);
    $response->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
    $response->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, Origin');
    $response->header('Access-Control-Allow-Credentials', 'true');
    $response->header('Access-Control-Max-Age', '86400');
    $response->header('Access-Control-Expose-Headers', 'Content-Length, Content-Type');

    if (($request->server['request_method'] ?? '') === 'OPTIONS') {
        $response->status(204);
        $response->end('');
        return;
    }
}



function sendResponse(Response $response, mixed $result, ?Request $request = null): void
{
    // تنظیم CORS headers اگر request موجود باشد
    if ($request) {
        setCorsHeaders($response, $request);
    }
    
    if (is_string($result)) {
        $response->header('Content-Type', 'text/html; charset=utf-8');
        $response->status(200);
        $response->end($result);
        return;
    }

    // Check if result has custom content_type (for plain text responses)
    if (is_array($result) && isset($result['content_type'])) {
        $contentType = $result['content_type'];
        $response->header('Content-Type', $contentType . '; charset=utf-8');
        $response->status(200);
        
        // For plain text, return data directly
        if ($contentType === 'text/plain' && isset($result['data'])) {
            $response->end($result['data']);
            return;
        }
    }

    $response->header('Content-Type', 'application/json; charset=utf-8');
    if (is_array($result) && isset($result['headers']) && is_array($result['headers'])) {
        foreach ($result['headers'] as $key => $value) {
            if ($key !== '' && $value !== null) {
                $response->header($key, (string)$value);
            }
        }
    }
    $httpStatus = (int)($result['status'] ?? 200);
    $response->status($httpStatus);

    $body = is_array($result) && isset($result['success'])
        ? json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        : json_encode([
            'success' => true,
            'status' => 200,
            'data' => $result,
            'message' => '',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    // فقط برای /contents/full از gzip استفاده کن (پاسخ‌های بزرگ)
    $uri = $request->server['request_uri'] ?? '';
    $isFullContents = str_contains($uri, '/contents/full');
    
    if ($request && $isFullContents && str_contains(strtolower($request->header['accept-encoding'] ?? ''), 'gzip')) {
        $body = gzencode($body, 6);
        $response->header('Content-Encoding', 'gzip');
    }
    $response->end($body);
}

function handleException(Response $response, \Throwable $e, bool $debug, ?Request $request = null): void
{
    // تنظیم CORS headers اگر request موجود باشد
    if ($request) {
        setCorsHeaders($response, $request);
    }
    
    error_log((string)$e);
    $response->status(500);
    $response->header('Content-Type', 'application/json; charset=utf-8');

    $msg = $debug ? $e->getMessage() : 'Internal Server Error';
    $response->end(json_encode([
        'success' => false,
        'status' => 500,
        'message' => $msg,
        'data' => $debug ? ['trace' => $e->getTraceAsString()] : null,
    ], JSON_UNESCAPED_UNICODE));
}

function extractApiVersion(string $uri): string
{
    $uri = explode('?', $uri, 2)[0];

    if (str_starts_with($uri, '/api/v')) {
        $rest = substr($uri, 5); // after "/api/"
        $slash = strpos($rest, '/');
        if ($slash !== false) {
            $ver = substr($rest, 0, $slash);
            if (strlen($ver) >= 2 && $ver[0] === 'v' && ctype_digit(substr($ver, 1))) {
                return $ver;
            }
        }
    }

    if (str_starts_with($uri, '/v')) {
        $rest = substr($uri, 1); // after "/"
        $slash = strpos($rest, '/');
        if ($slash !== false) {
            $ver = substr($rest, 0, $slash);
            if (strlen($ver) >= 2 && $ver[0] === 'v' && ctype_digit(substr($ver, 1))) {
                return $ver;
            }
        }
    }

    return 'v1';
}

function extractPath(string $uri): string
{
    $uri = explode('?', $uri, 2)[0];

    if (str_starts_with($uri, '/api/v')) {
        $rest = substr($uri, 5); // after "/api/"
        $slash = strpos($rest, '/');
        if ($slash !== false) {
            $path = substr($rest, $slash);
            return $path !== '' ? $path : '/';
        }
    }

    if (str_starts_with($uri, '/v')) {
        $rest = substr($uri, 1); // after "/"
        $slash = strpos($rest, '/');
        if ($slash !== false) {
            $path = substr($rest, $slash);
            return $path !== '' ? $path : '/';
        }
    }

    return $uri ?: '/';
}
